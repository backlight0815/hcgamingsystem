import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/network/api_client.dart';
import '../../core/security/access_policy.dart';
import '../../models/app_user.dart';
import '../../models/capital_transaction.dart';
import '../../models/mt5_sync_models.dart';
import '../../models/trader_account_profile.dart';
import '../../models/trading_account.dart';
import '../../models/trading_data_source.dart';
import '../../models/trading_journal_entry.dart';
import '../../repositories/trading_repository.dart';
import '../../services/trading_analytics.dart';
import '../../services/trading_data_source_service.dart';
import '../../services/trading_report_service.dart';
import '../../shared/empty_state.dart';
import '../../shared/app_feedback.dart';
import '../../shared/formatters.dart';
import '../../shared/trading_data_builder.dart';
import '../access/access_required_view.dart';

class AccountScreen extends StatelessWidget {
  const AccountScreen({
    super.key,
    required this.user,
    required this.repository,
    this.onViewAccessStatus,
  });

  final AppUser user;
  final TradingRepository repository;
  final VoidCallback? onViewAccessStatus;

  @override
  Widget build(BuildContext context) {
    if (ClientAccessPolicy.isRestricted(user, AppFeatureArea.accounts)) {
      return AccessRequiredView(
        featureName: 'Accounts',
        user: user,
        onViewStatus: onViewAccessStatus,
      );
    }
    return StreamBuilder<TraderAccountProfile>(
      stream: repository.accountProfileFor(user.uid),
      builder: (context, profileSnapshot) {
        final profile =
            profileSnapshot.data ?? TraderAccountProfile.empty(user.uid);

        return TradingDataBuilder(
          user: user,
          repository: repository,
          builder: (context, journals, capitals, pairs, mode) {
            final summary = TradingAnalytics().summarize(
              journals: journals,
              capitals: capitals,
            );

            return StreamBuilder<List<TradingAccount>>(
              stream: repository.tradingAccountsFor(user.uid),
              builder: (context, accountSnapshot) => ListView(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 24),
                children: [
                  _AccountStatusCard(
                    user: user,
                    profile: profile,
                    onEdit: profile.canSubmit
                        ? () => _openProfileDialog(context, profile)
                        : null,
                  ),
                  const SizedBox(height: 14),
                  _TradingAccountsCard(
                    accounts: accountSnapshot.data ?? const [],
                    loading:
                        accountSnapshot.connectionState ==
                            ConnectionState.waiting &&
                        !accountSnapshot.hasData,
                    onAdd: () => _openTradingAccountDialog(context),
                    onEdit: (account) =>
                        _openTradingAccountDialog(context, account: account),
                    onDelete: (account) =>
                        _confirmDeleteTradingAccount(context, account),
                  ),
                  const SizedBox(height: 14),
                  _CapitalOverviewResolver(
                    uid: user.uid,
                    repository: repository,
                    mode: mode,
                    manualBalance: summary.currentBalance,
                    manualDeposits: summary.totalDeposits,
                    manualWithdrawals: summary.totalWithdrawals,
                    onAddManual: () => _openCapitalDialog(
                      context,
                      journals: journals,
                      capitals: capitals,
                    ),
                  ),
                  if (mode == TradingDataDisplayMode.mt5Only) ...[
                    const SizedBox(height: 14),
                    _MainMt5AccountDetails(
                      uid: user.uid,
                      repository: repository,
                    ),
                  ],
                  if (mode == TradingDataDisplayMode.manualOnly) ...[
                    const SizedBox(height: 14),
                    _CapitalList(
                      capitals: capitals,
                      onEdit: (capital) => _openCapitalDialog(
                        context,
                        journals: journals,
                        capitals: capitals,
                        capital: capital,
                      ),
                      onDelete: (capital) =>
                          _confirmDeleteCapital(context, capital),
                    ),
                  ],
                ],
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _openProfileDialog(
    BuildContext context,
    TraderAccountProfile profile,
  ) async {
    await showDialog<void>(
      context: context,
      builder: (_) => _AccountProfileDialog(
        user: user,
        repository: repository,
        profile: profile,
      ),
    );
  }

  Future<void> _openCapitalDialog(
    BuildContext context, {
    required List<TradingJournalEntry> journals,
    required List<CapitalTransaction> capitals,
    CapitalTransaction? capital,
  }) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => _CapitalDialog(
        user: user,
        repository: repository,
        journals: journals,
        capitals: capitals,
        capital: capital,
      ),
    );
    if (saved == true && context.mounted) {
      AppFeedback.success(
        context,
        capital == null
            ? 'Capital transaction saved.'
            : 'Capital transaction updated.',
      );
    }
  }

  Future<void> _confirmDeleteCapital(
    BuildContext context,
    CapitalTransaction capital,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.l10n.text('Delete capital record')),
        content: Text(
          '${context.l10n.text(capital.typeLabel)} ${formatMoney(capital.amount)}',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(context.l10n.text('Delete')),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await repository.deleteCapitalTransaction(
        uid: user.uid,
        capitalId: capital.id,
      );
    }
  }

  Future<void> _openTradingAccountDialog(
    BuildContext context, {
    TradingAccount? account,
  }) async {
    await showDialog<void>(
      context: context,
      builder: (_) => _TradingAccountDialog(
        user: user,
        repository: repository,
        account: account,
      ),
    );
  }

  Future<void> _confirmDeleteTradingAccount(
    BuildContext context,
    TradingAccount account,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.l10n.text('Archive trading account?')),
        content: Text(
          context.l10n.text(
            '{name} will be removed from active account selection. Existing journal records remain safe.',
            {'name': account.displayName},
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(context.l10n.text('Archive')),
          ),
        ],
      ),
    );
    if (confirmed == true) {
      await repository.deleteTradingAccount(
        uid: user.uid,
        accountId: account.id,
      );
    }
  }
}

class _MainMt5AccountDetails extends StatelessWidget {
  const _MainMt5AccountDetails({required this.uid, required this.repository});

  final String uid;
  final TradingRepository repository;

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<Mt5Account?>(
      stream: repository.mainMt5AccountFor(uid),
      builder: (context, snapshot) {
        if (!snapshot.hasData &&
            snapshot.connectionState == ConnectionState.waiting) {
          return const Card(
            child: Padding(
              padding: EdgeInsets.all(18),
              child: Center(child: CircularProgressIndicator()),
            ),
          );
        }
        final account = snapshot.data;
        if (account == null) {
          return const EmptyState(
            icon: Icons.account_balance_outlined,
            title: 'Select a main MT5 account to continue.',
          );
        }
        final lastSync =
            account.lastSuccessfulSyncAt ??
            account.lastSyncTime ??
            account.updatedAt;
        final status = account.isConnected ? 'Connected' : 'Disconnected';
        return Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.l10n.text('Main Account'),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 12),
                _AccountDetailRow('Account Name', account.label),
                _AccountDetailRow('Account Number', '${account.login}'),
                _AccountDetailRow('Broker Server', account.brokerServer),
                _AccountDetailRow(
                  'Currency',
                  account.currency.trim().isEmpty ? '-' : account.currency,
                ),
                _AccountDetailRow(
                  'Leverage',
                  account.leverage == null ? '-' : '1:${account.leverage}',
                ),
                _AccountDetailRow(
                  'Balance',
                  formatCurrencyMoney(
                    mt5AccountCapitalBalance(account),
                    account.currency,
                  ),
                ),
                _AccountDetailRow(
                  'Equity',
                  account.equity == null
                      ? '-'
                      : formatCurrencyMoney(account.equity!, account.currency),
                ),
                _AccountDetailRow(
                  'Margin',
                  account.margin == null
                      ? '-'
                      : formatCurrencyMoney(account.margin!, account.currency),
                ),
                _AccountDetailRow(
                  'Free Margin',
                  account.freeMargin == null
                      ? '-'
                      : formatCurrencyMoney(
                          account.freeMargin!,
                          account.currency,
                        ),
                ),
                _AccountDetailRow('Connection Status', status),
                _AccountDetailRow(
                  'Last sync time',
                  lastSync == null ? '-' : formatDateTime(lastSync),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _AccountDetailRow extends StatelessWidget {
  const _AccountDetailRow(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 132,
            child: Text(
              context.l10n.text(label),
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ),
          Expanded(child: Text(context.l10n.text(value))),
        ],
      ),
    );
  }
}

class _CapitalOverviewResolver extends StatelessWidget {
  const _CapitalOverviewResolver({
    required this.uid,
    required this.repository,
    required this.mode,
    required this.manualBalance,
    required this.manualDeposits,
    required this.manualWithdrawals,
    required this.onAddManual,
  });

  final String uid;
  final TradingRepository repository;
  final TradingDataDisplayMode mode;
  final double manualBalance;
  final double manualDeposits;
  final double manualWithdrawals;
  final VoidCallback onAddManual;

  @override
  Widget build(BuildContext context) {
    if (mode == TradingDataDisplayMode.manualOnly) {
      return _CapitalOverviewCard(
        balance: manualBalance,
        deposits: manualDeposits,
        withdrawals: manualWithdrawals,
        currency: '',
        sourceLabel: context.l10n.text('Manual Trading Journal'),
        onAdd: onAddManual,
      );
    }
    return StreamBuilder<Mt5Account?>(
      stream: repository.mainMt5AccountFor(uid),
      builder: (context, snapshot) {
        if (!snapshot.hasData &&
            snapshot.connectionState == ConnectionState.waiting) {
          return const Card(
            child: Padding(
              padding: EdgeInsets.all(18),
              child: Center(child: CircularProgressIndicator()),
            ),
          );
        }
        final account = snapshot.data;
        return _CapitalOverviewCard(
          balance: mt5AccountCapitalBalance(account),
          deposits: 0,
          withdrawals: 0,
          currency: account?.currency ?? '',
          sourceLabel: account == null
              ? context.l10n.text('No main MT5 account selected.')
              : context.l10n.text('Main MT5: {account}', {
                  'account': account.label,
                }),
          onAdd: null,
        );
      },
    );
  }
}

class _AccountStatusCard extends StatelessWidget {
  const _AccountStatusCard({
    required this.user,
    required this.profile,
    required this.onEdit,
  });

  final AppUser user;
  final TraderAccountProfile profile;
  final VoidCallback? onEdit;

  @override
  Widget build(BuildContext context) {
    final tone = switch (profile.status) {
      'approved' => const Color(0xFF17855B),
      'pending' => const Color(0xFFD99A17),
      'not_submitted' => Theme.of(context).colorScheme.primary,
      _ => const Color(0xFFC23B36),
    };

    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: tone.withValues(alpha: .12),
                  child: Icon(Icons.manage_accounts_outlined, color: tone),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        user.label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      Text(
                        context.l10n.text(profile.statusLabel),
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: tone,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: context.l10n.text(
                    profile.isPending
                        ? 'Your broker verification is awaiting review.'
                        : 'Manage trading account',
                  ),
                  onPressed: onEdit,
                  icon: const Icon(Icons.edit_note),
                ),
              ],
            ),
            const Divider(height: 28),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _InfoChip(
                  icon: Icons.forum_outlined,
                  label: profile.discordUsername.isEmpty
                      ? 'Discord missing'
                      : profile.discordUsername,
                ),
                _InfoChip(
                  icon: Icons.account_balance_outlined,
                  label: profile.hasDeposit
                      ? formatMoney(profile.depositAmount)
                      : 'No deposit',
                ),
                _InfoChip(
                  icon: Icons.verified_user_outlined,
                  label: profile.isClient ? 'Client' : 'Not client',
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _CapitalOverviewCard extends StatelessWidget {
  const _CapitalOverviewCard({
    required this.balance,
    required this.deposits,
    required this.withdrawals,
    required this.currency,
    required this.sourceLabel,
    required this.onAdd,
  });

  final double balance;
  final double deposits;
  final double withdrawals;
  final String currency;
  final String sourceLabel;
  final VoidCallback? onAdd;

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
            Row(
              children: [
                Expanded(
                  child: Text(
                    context.l10n.text('Capital'),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: context.l10n.text('Add capital'),
                  onPressed: onAdd,
                  icon: const Icon(Icons.add_card_outlined),
                ),
              ],
            ),
            Text(
              sourceLabel,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
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
                  child: _Figure(
                    label: 'Deposits',
                    value: money(deposits),
                    color: const Color(0xFF17855B),
                  ),
                ),
                Expanded(
                  child: _Figure(
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

class _CapitalList extends StatelessWidget {
  const _CapitalList({
    required this.capitals,
    required this.onEdit,
    required this.onDelete,
  });

  final List<CapitalTransaction> capitals;
  final ValueChanged<CapitalTransaction> onEdit;
  final ValueChanged<CapitalTransaction> onDelete;

  @override
  Widget build(BuildContext context) {
    if (capitals.isEmpty) {
      return const SizedBox(
        height: 260,
        child: EmptyState(
          icon: Icons.account_balance_wallet_outlined,
          title: 'No capital transactions yet',
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.l10n.text('Recent Transactions'),
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 10),
        for (final capital in capitals.take(8))
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Card(
              elevation: 0,
              child: ListTile(
                leading: Icon(
                  capital.isDeposit ? Icons.south_west : Icons.north_east,
                  color: capital.isDeposit
                      ? const Color(0xFF17855B)
                      : const Color(0xFFC23B36),
                ),
                title: Text(context.l10n.text(capital.typeLabel)),
                subtitle: Text(
                  [
                    formatDate(capital.depositDate),
                    if (capital.notes.trim().isNotEmpty) capital.notes.trim(),
                  ].join('  |  '),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                trailing: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      formatMoney(capital.amount),
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    PopupMenuButton<String>(
                      tooltip: context.l10n.text('Capital actions'),
                      onSelected: (value) {
                        if (value == 'edit') {
                          onEdit(capital);
                        } else if (value == 'delete') {
                          onDelete(capital);
                        }
                      },
                      itemBuilder: (context) => [
                        PopupMenuItem(
                          value: 'edit',
                          child: ListTile(
                            leading: const Icon(Icons.edit_outlined),
                            title: Text(context.l10n.text('Edit')),
                          ),
                        ),
                        PopupMenuItem(
                          value: 'delete',
                          child: ListTile(
                            leading: const Icon(Icons.delete_outline),
                            title: Text(context.l10n.text('Delete')),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class _AccountProfileDialog extends StatefulWidget {
  const _AccountProfileDialog({
    required this.user,
    required this.repository,
    required this.profile,
  });

  final AppUser user;
  final TradingRepository repository;
  final TraderAccountProfile profile;

  @override
  State<_AccountProfileDialog> createState() => _AccountProfileDialogState();
}

class _AccountProfileDialogState extends State<_AccountProfileDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _discordController;
  late final TextEditingController _brokerUidController;
  late final TextEditingController _brokerEmailController;
  late final TextEditingController _depositController;
  late final TextEditingController _noteController;
  late bool _isClient;
  late bool _hasDeposit;
  var _saving = false;
  PlatformFile? _document;

  @override
  void initState() {
    super.initState();
    final profile = widget.profile;
    _discordController = TextEditingController(text: profile.discordUsername);
    _brokerUidController = TextEditingController(text: profile.brokerUid);
    _brokerEmailController = TextEditingController(text: profile.brokerEmail);
    _depositController = TextEditingController(
      text: profile.depositAmount > 0
          ? profile.depositAmount.toStringAsFixed(2)
          : '',
    );
    _noteController = TextEditingController(text: profile.traderNote);
    _isClient = profile.isClient;
    _hasDeposit = profile.hasDeposit;
  }

  @override
  void dispose() {
    _discordController.dispose();
    _brokerUidController.dispose();
    _brokerEmailController.dispose();
    _depositController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(context.l10n.text('Trading verification')),
      content: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _isClient,
                onChanged: (value) => setState(() => _isClient = value),
                title: Text(context.l10n.text('HC client')),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _hasDeposit,
                onChanged: (value) => setState(() => _hasDeposit = value),
                title: Text(context.l10n.text('Deposit completed')),
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _discordController,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Discord Username'),
                  prefixIcon: const Icon(Icons.alternate_email),
                ),
                validator: (value) => (value ?? '').trim().isEmpty
                    ? context.l10n.text('Required')
                    : null,
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: _pickDocument,
                icon: const Icon(Icons.upload_file_outlined),
                label: Text(
                  _document == null
                      ? context.l10n.text('Select verification document')
                      : _document!.name,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                context.l10n.text(
                  'PDF, JPG or PNG. Keep the file under 800 KB.',
                ),
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _brokerUidController,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Broker UID'),
                  prefixIcon: const Icon(Icons.badge_outlined),
                ),
                validator: (value) => (value ?? '').trim().isEmpty
                    ? context.l10n.text('Required')
                    : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _brokerEmailController,
                keyboardType: TextInputType.emailAddress,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Broker Email'),
                  prefixIcon: const Icon(Icons.mail_outline),
                ),
                validator: (value) {
                  final text = (value ?? '').trim();
                  return text.contains('@')
                      ? null
                      : context.l10n.text('Enter broker email');
                },
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _depositController,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: InputDecoration(
                  labelText: context.l10n.text('Deposit Amount'),
                  prefixIcon: const Icon(Icons.payments_outlined),
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _noteController,
                minLines: 2,
                maxLines: 4,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Trader Note'),
                  alignLabelWithHint: true,
                  prefixIcon: const Icon(Icons.notes_outlined),
                ),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(),
          child: Text(context.l10n.text('Cancel')),
        ),
        FilledButton.icon(
          onPressed: _saving ? null : _save,
          icon: _saving
              ? const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.send_outlined),
          label: Text(context.l10n.text('Submit')),
        ),
      ],
    );
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    if (_document == null && widget.profile.status != 'approved') {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.l10n.text('Select a PDF or image document.')),
        ),
      );
      return;
    }
    setState(() => _saving = true);
    try {
      final file = _document;
      await widget.repository.saveAccountProfile(
        TraderAccountProfile(
          id: 'profile',
          userId: widget.user.uid,
          status: widget.profile.isApproved ? 'approved' : 'pending',
          isClient: _isClient,
          hasDeposit: _hasDeposit,
          depositAmount: double.tryParse(_depositController.text) ?? 0,
          discordUsername: _discordController.text.trim(),
          brokerUid: _brokerUidController.text.trim(),
          brokerEmail: _brokerEmailController.text.trim(),
          traderNote: _noteController.text.trim(),
          submittedAt: DateTime.now(),
        ),
        document: file == null
            ? null
            : ApiUploadFile(
                field: 'document',
                filename: file.name,
                path: file.path,
                bytes: file.bytes,
              ),
      );

      if (mounted) {
        final messenger = ScaffoldMessenger.of(context);
        Navigator.of(context).pop();
        messenger.showSnackBar(
          SnackBar(
            content: Text(
              context.l10n.text('Verification submitted for review.'),
            ),
          ),
        );
      }
    } catch (error) {
      if (mounted) {
        AppFeedback.error(context, error);
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  Future<void> _pickDocument() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'],
      withData: true,
    );
    if (result != null && mounted) {
      final file = result.files.single;
      if (file.size > 10 * 1024 * 1024) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              context.l10n.text('Choose a document smaller than 10 MB.'),
            ),
          ),
        );
        return;
      }
      setState(() => _document = file);
    }
  }
}

class _CapitalDialog extends StatefulWidget {
  const _CapitalDialog({
    required this.user,
    required this.repository,
    required this.journals,
    required this.capitals,
    this.capital,
  });

  final AppUser user;
  final TradingRepository repository;
  final List<TradingJournalEntry> journals;
  final List<CapitalTransaction> capitals;
  final CapitalTransaction? capital;

  @override
  State<_CapitalDialog> createState() => _CapitalDialogState();
}

class _CapitalDialogState extends State<_CapitalDialog> {
  final _formKey = GlobalKey<FormState>();
  final _reportService = TradingReportService();
  late final TextEditingController _amountController;
  late final TextEditingController _notesController;
  late int _type;
  late DateTime _date;
  var _saving = false;

  bool get _isEdit => widget.capital != null;

  @override
  void initState() {
    super.initState();
    final capital = widget.capital;
    _type = capital?.type ?? 1;
    _date = capital?.depositDate ?? DateTime.now();
    _amountController = TextEditingController(
      text: capital == null ? '' : capital.absoluteAmount.toStringAsFixed(2),
    );
    _notesController = TextEditingController(text: capital?.notes ?? '');
  }

  @override
  void dispose() {
    _amountController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(context.l10n.text(_isEdit ? 'Edit Capital' : 'Capital')),
      content: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SegmentedButton<int>(
                segments: [
                  ButtonSegment(
                    value: 1,
                    icon: const Icon(Icons.south_west),
                    label: Text(context.l10n.text('Deposit')),
                  ),
                  ButtonSegment(
                    value: 2,
                    icon: const Icon(Icons.north_east),
                    label: Text(context.l10n.text('Withdraw')),
                  ),
                ],
                selected: {_type},
                onSelectionChanged: _isEdit
                    ? null
                    : (selection) {
                        setState(() => _type = selection.first);
                      },
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _amountController,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: InputDecoration(
                  labelText: context.l10n.text('Amount'),
                  prefixIcon: const Icon(Icons.payments_outlined),
                ),
                validator: (value) {
                  final amount = double.tryParse(value ?? '');
                  return amount != null && amount > 0
                      ? null
                      : context.l10n.text('Enter amount');
                },
              ),
              const SizedBox(height: 12),
              InkWell(
                borderRadius: BorderRadius.circular(8),
                onTap: _pickDate,
                child: InputDecorator(
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Date'),
                    prefixIcon: const Icon(Icons.event_outlined),
                  ),
                  child: Text(formatDate(_date)),
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _notesController,
                minLines: 2,
                maxLines: 4,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Notes'),
                  alignLabelWithHint: true,
                  prefixIcon: const Icon(Icons.notes_outlined),
                ),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(),
          child: Text(context.l10n.text('Cancel')),
        ),
        FilledButton.icon(
          onPressed: _saving ? null : _save,
          icon: _saving
              ? const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.save_outlined),
          label: Text(context.l10n.text('Save')),
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
      if (_type == 2) {
        final allowed = _isEdit
            ? _hasBalanceForEditedWithdrawal(amount)
            : _reportService.withdrawalEligibility(
                journals: widget.journals,
                capitals: widget.capitals,
                requestedAmount: amount,
              );

        if (!allowed.eligible) {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(context.l10n.text(allowed.message))),
            );
          }
          return;
        }
      }

      await widget.repository.upsertCapitalTransaction(
        CapitalTransaction(
          id: widget.capital?.id ?? '',
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
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.l10n.text(error.toString()))),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  WithdrawalEligibility _hasBalanceForEditedWithdrawal(double amount) {
    final report = _reportService.build(
      journals: widget.journals,
      capitals: widget.capitals,
    );
    final existingAmount = widget.capital?.amount ?? 0;
    final balanceExcludingThisRecord =
        report.summary.currentBalance - existingAmount;
    if (balanceExcludingThisRecord - amount >= 0) {
      return WithdrawalEligibility(
        eligible: true,
        message: '',
        bestDayRule: report.summary.bestDayRule,
        grossProfitRule: report.summary.grossProfitRule,
      );
    }

    return WithdrawalEligibility(
      eligible: false,
      message: 'Withdrawal failed: insufficient balance.',
      bestDayRule: report.summary.bestDayRule,
      grossProfitRule: report.summary.grossProfitRule,
    );
  }
}

class _InfoChip extends StatelessWidget {
  const _InfoChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Chip(
      avatar: Icon(icon, size: 18),
      label: Text(context.l10n.text(label)),
    );
  }
}

class _TradingAccountsCard extends StatelessWidget {
  const _TradingAccountsCard({
    required this.accounts,
    required this.loading,
    required this.onAdd,
    required this.onEdit,
    required this.onDelete,
  });

  final List<TradingAccount> accounts;
  final bool loading;
  final VoidCallback onAdd;
  final ValueChanged<TradingAccount> onEdit;
  final ValueChanged<TradingAccount> onDelete;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    context.l10n.text('Broker Accounts'),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                FilledButton.tonalIcon(
                  onPressed: onAdd,
                  icon: const Icon(Icons.add, size: 18),
                  label: Text(context.l10n.text('Add')),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              context.l10n.text(
                'Separate live, demo, prop-firm, and funded performance.',
              ),
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 14),
            if (loading)
              const LinearProgressIndicator()
            else if (accounts.isEmpty)
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.surfaceContainerLow,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.account_balance_outlined),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        context.l10n.text(
                          'Add your first broker account to organize journals and imports.',
                        ),
                      ),
                    ),
                  ],
                ),
              )
            else
              for (final account in accounts)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      border: Border.all(
                        color: Theme.of(context).colorScheme.outlineVariant,
                      ),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Flexible(
                                        child: Text(
                                          account.displayName,
                                          overflow: TextOverflow.ellipsis,
                                          style: const TextStyle(
                                            fontWeight: FontWeight.w900,
                                          ),
                                        ),
                                      ),
                                      if (account.isPrimary) ...[
                                        const SizedBox(width: 6),
                                        Chip(
                                          visualDensity: VisualDensity.compact,
                                          label: Text(
                                            context.l10n.text('Primary'),
                                          ),
                                        ),
                                      ],
                                    ],
                                  ),
                                  Text(
                                    '${account.broker} · ${account.platform} · ${account.typeLabel}',
                                  ),
                                ],
                              ),
                            ),
                            PopupMenuButton<String>(
                              onSelected: (value) => value == 'edit'
                                  ? onEdit(account)
                                  : onDelete(account),
                              itemBuilder: (_) => [
                                PopupMenuItem(
                                  value: 'edit',
                                  child: Text(
                                    context.l10n.text('Edit account'),
                                  ),
                                ),
                                PopupMenuItem(
                                  value: 'delete',
                                  child: Text(
                                    context.l10n.text('Archive account'),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        Row(
                          children: [
                            Expanded(
                              child: _Figure(
                                label: 'Calculated balance',
                                value:
                                    '${account.currency} ${account.calculatedBalance.toStringAsFixed(2)}',
                                color: Theme.of(context).colorScheme.primary,
                              ),
                            ),
                            Expanded(
                              child: _Figure(
                                label: 'Net P/L',
                                value:
                                    '${account.currency} ${account.netProfitLoss.toStringAsFixed(2)}',
                                color: account.netProfitLoss >= 0
                                    ? const Color(0xFF17855B)
                                    : const Color(0xFFC23B36),
                              ),
                            ),
                            Expanded(
                              child: _Figure(
                                label: 'Trades',
                                value: '${account.journalsCount}',
                                color: Theme.of(context).colorScheme.onSurface,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
          ],
        ),
      ),
    );
  }
}

class _TradingAccountDialog extends StatefulWidget {
  const _TradingAccountDialog({
    required this.user,
    required this.repository,
    this.account,
  });

  final AppUser user;
  final TradingRepository repository;
  final TradingAccount? account;

  @override
  State<_TradingAccountDialog> createState() => _TradingAccountDialogState();
}

class _TradingAccountDialogState extends State<_TradingAccountDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _broker;
  late final TextEditingController _number;
  late final TextEditingController _currency;
  late final TextEditingController _balance;
  late final TextEditingController _equity;
  late final TextEditingController _leverage;
  late final TextEditingController _notes;
  late String _platform;
  late String _type;
  late String _status;
  late bool _primary;
  var _saving = false;

  @override
  void initState() {
    super.initState();
    final account = widget.account;
    _name = TextEditingController(text: account?.name ?? 'Main Account');
    _broker = TextEditingController(text: account?.broker ?? '');
    _number = TextEditingController(text: account?.accountNumber ?? '');
    _currency = TextEditingController(text: account?.currency ?? 'USD');
    _balance = TextEditingController(
      text: account == null ? '' : '${account.startingBalance}',
    );
    _equity = TextEditingController(
      text: account?.currentEquity == null ? '' : '${account!.currentEquity}',
    );
    _leverage = TextEditingController(text: account?.leverage ?? '1:100');
    _notes = TextEditingController(text: account?.notes ?? '');
    _platform = account?.platform ?? 'MT5';
    _type = account?.accountType ?? 'live';
    _status = account?.status ?? 'active';
    _primary = account?.isPrimary ?? false;
  }

  @override
  void dispose() {
    for (final controller in [
      _name,
      _broker,
      _number,
      _currency,
      _balance,
      _equity,
      _leverage,
      _notes,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(
        context.l10n.text(
          widget.account == null ? 'Add broker account' : 'Edit account',
        ),
      ),
      content: SizedBox(
        width: 520,
        child: Form(
          key: _formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextFormField(
                  controller: _name,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Account name'),
                  ),
                  validator: (value) => _required(context, value),
                ),
                const SizedBox(height: 10),
                TextFormField(
                  controller: _broker,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Broker / firm'),
                  ),
                  validator: (value) => _required(context, value),
                ),
                const SizedBox(height: 10),
                TextFormField(
                  controller: _number,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Account number (optional)'),
                  ),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: _platform,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Platform'),
                        ),
                        items:
                            const [
                                  'MT4',
                                  'MT5',
                                  'cTrader',
                                  'TradingView',
                                  'Other',
                                ]
                                .map(
                                  (value) => DropdownMenuItem(
                                    value: value,
                                    child: Text(value),
                                  ),
                                )
                                .toList(),
                        onChanged: (value) => _platform = value ?? 'MT5',
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: _type,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Type'),
                        ),
                        items:
                            const {
                                  'demo': 'Demo',
                                  'live': 'Live',
                                  'prop_firm': 'Prop Firm',
                                  'funded': 'Funded',
                                }.entries
                                .map(
                                  (item) => DropdownMenuItem(
                                    value: item.key,
                                    child: Text(context.l10n.text(item.value)),
                                  ),
                                )
                                .toList(),
                        onChanged: (value) => _type = value ?? 'live',
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _currency,
                        textCapitalization: TextCapitalization.characters,
                        maxLength: 3,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Currency'),
                          counterText: '',
                        ),
                        validator: (value) => (value ?? '').trim().length == 3
                            ? null
                            : context.l10n.text('Use 3 letters'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: TextFormField(
                        controller: _leverage,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Leverage'),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: _numberField(
                        context,
                        _balance,
                        'Starting balance',
                        required: true,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _numberField(context, _equity, 'Current equity'),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  initialValue: _status,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Status'),
                  ),
                  items:
                      const ['active', 'paused', 'passed', 'failed', 'archived']
                          .map(
                            (value) => DropdownMenuItem(
                              value: value,
                              child: Text(context.l10n.text(_titleCase(value))),
                            ),
                          )
                          .toList(),
                  onChanged: (value) => _status = value ?? 'active',
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(context.l10n.text('Primary account')),
                  subtitle: Text(
                    context.l10n.text(
                      'Default account for new journal records',
                    ),
                  ),
                  value: _primary,
                  onChanged: (value) => setState(() => _primary = value),
                ),
                TextField(
                  controller: _notes,
                  minLines: 2,
                  maxLines: 4,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Notes'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.pop(context),
          child: Text(context.l10n.text('Cancel')),
        ),
        FilledButton(
          onPressed: _saving ? null : _save,
          child: Text(
            context.l10n.text(_saving ? 'Saving...' : 'Save account'),
          ),
        ),
      ],
    );
  }

  TextFormField _numberField(
    BuildContext context,
    TextEditingController controller,
    String label, {
    bool required = false,
  }) => TextFormField(
    controller: controller,
    keyboardType: const TextInputType.numberWithOptions(decimal: true),
    decoration: InputDecoration(labelText: context.l10n.text(label)),
    validator: (value) {
      if (!required && (value ?? '').trim().isEmpty) return null;
      final parsed = double.tryParse(value ?? '');
      return parsed != null && parsed >= 0
          ? null
          : context.l10n.text('Enter a valid amount');
    },
  );

  String? _required(BuildContext context, String? value) =>
      (value ?? '').trim().isEmpty ? context.l10n.text('Required') : null;

  String _titleCase(String value) =>
      value.isEmpty ? value : value[0].toUpperCase() + value.substring(1);

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      await widget.repository.upsertTradingAccount(
        TradingAccount(
          id: widget.account?.id ?? '',
          userId: widget.user.uid,
          name: _name.text.trim(),
          broker: _broker.text.trim(),
          accountNumber: _number.text.trim(),
          platform: _platform,
          accountType: _type,
          currency: _currency.text.trim().toUpperCase(),
          startingBalance: double.parse(_balance.text),
          currentEquity: _equity.text.trim().isEmpty
              ? null
              : double.parse(_equity.text),
          leverage: _leverage.text.trim(),
          status: _status,
          isPrimary: _primary,
          notes: _notes.text.trim(),
        ),
      );
      if (mounted) Navigator.pop(context);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.l10n.text(error.toString()))),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }
}

class _Figure extends StatelessWidget {
  const _Figure({
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
          context.l10n.text(label),
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 4),
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
