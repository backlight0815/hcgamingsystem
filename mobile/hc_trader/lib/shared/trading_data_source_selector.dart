import 'package:flutter/material.dart';

import '../core/localization/app_localizations.dart';
import '../core/security/permission_service.dart';
import '../models/app_user.dart';
import '../models/mt5_sync_models.dart';
import '../models/trading_data_source.dart';
import '../repositories/trading_repository.dart';
import 'formatters.dart';

class TradingDataSourceSelector extends StatelessWidget {
  const TradingDataSourceSelector({
    super.key,
    required this.user,
    required this.repository,
    required this.mode,
  });

  final AppUser user;
  final TradingRepository repository;
  final TradingDataDisplayMode mode;

  @override
  Widget build(BuildContext context) {
    final canUseMt5 = PermissionService(user).can(AppPermission.mt5SyncOwn);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.l10n.text('Trading data source'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            if (canUseMt5)
              SegmentedButton<TradingDataDisplayMode>(
                segments: [
                  ButtonSegment(
                    value: TradingDataDisplayMode.manualOnly,
                    label: Text(context.l10n.text('Manual Trading Journal')),
                    icon: const Icon(Icons.edit_note_outlined),
                  ),
                  ButtonSegment(
                    value: TradingDataDisplayMode.mt5Only,
                    label: Text(context.l10n.text('MT5 Synchronized Journal')),
                    icon: const Icon(Icons.sync_alt_outlined),
                  ),
                ],
                selected: {mode},
                onSelectionChanged: (selection) {
                  repository.setTradingDataDisplayMode(
                    uid: user.uid,
                    mode: selection.first,
                  );
                },
              )
            else
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.edit_note_outlined),
                title: Text(context.l10n.text('Manual Trading Journal')),
                subtitle: Text(
                  context.l10n.text(
                    'MT5 synchronization becomes available after client access is approved.',
                  ),
                ),
              ),
            const SizedBox(height: 8),
            Text(
              context.l10n.text(
                mode == TradingDataDisplayMode.mt5Only
                    ? 'Showing the selected main MT5 account only, including MT5 sync and MT5 Desktop imports. Manual journal records are excluded.'
                    : 'Showing manual journal records and manual spreadsheet imports only. MT5 sync and MT5 Desktop imports are excluded.',
              ),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
            if (mode == TradingDataDisplayMode.mt5Only) ...[
              const SizedBox(height: 10),
              _MainMt5AccountSummary(user: user, repository: repository),
            ],
          ],
        ),
      ),
    );
  }
}

class _MainMt5AccountSummary extends StatelessWidget {
  const _MainMt5AccountSummary({required this.user, required this.repository});

  final AppUser user;
  final TradingRepository repository;

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<Mt5Account?>(
      stream: repository.mainMt5AccountFor(user.uid),
      builder: (context, snapshot) {
        if (!snapshot.hasData) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const LinearProgressIndicator(minHeight: 2);
          }
          return _SummaryRow(
            icon: Icons.account_balance_outlined,
            text: context.l10n.text('No main MT5 account selected.'),
          );
        }
        final account = snapshot.data!;
        final lastSync =
            account.lastSuccessfulSyncAt ??
            account.lastSyncTime ??
            account.updatedAt;
        return _SummaryRow(
          icon: Icons.account_balance_wallet_outlined,
          text: context.l10n.text('Main MT5: {account} · Last sync: {time}', {
            'account': account.label,
            'time': lastSync == null
                ? context.l10n.text('Not synced yet')
                : formatDateTime(lastSync),
          }),
        );
      },
    );
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18),
        const SizedBox(width: 8),
        Expanded(
          child: Text(text, style: Theme.of(context).textTheme.bodySmall),
        ),
      ],
    );
  }
}
