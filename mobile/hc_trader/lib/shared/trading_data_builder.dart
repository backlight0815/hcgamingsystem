import 'package:flutter/material.dart';

import '../core/localization/app_localizations.dart';
import '../core/security/permission_service.dart';
import '../models/app_user.dart';
import '../models/capital_transaction.dart';
import '../models/mt5_sync_models.dart';
import '../models/trading_data_source.dart';
import '../models/trading_journal_entry.dart';
import '../models/trading_pair.dart';
import '../repositories/trading_repository.dart';
import '../services/trading_data_source_service.dart';
import 'app_feedback.dart';
import 'analytics/analytics_widgets.dart';

typedef TradingDataWidgetBuilder =
    Widget Function(
      BuildContext context,
      List<TradingJournalEntry> journals,
      List<CapitalTransaction> capitals,
      List<TradingPair> pairs,
      TradingDataDisplayMode mode,
    );

class TradingDataBuilder extends StatelessWidget {
  const TradingDataBuilder({
    super.key,
    required this.user,
    required this.repository,
    required this.builder,
    this.includePrivateTradingData = true,
    this.selectedMt5AccountId,
  });

  final AppUser user;
  final TradingRepository repository;
  final TradingDataWidgetBuilder builder;
  final bool includePrivateTradingData;

  /// `null` keeps the app-wide main account, `*` aggregates all active MT5
  /// accounts, and any other value scopes analytics to that account document.
  final String? selectedMt5AccountId;

  @override
  Widget build(BuildContext context) {
    if (!includePrivateTradingData) {
      return builder(
        context,
        const <TradingJournalEntry>[],
        const <CapitalTransaction>[],
        const <TradingPair>[],
        TradingDataDisplayMode.manualOnly,
      );
    }
    return StreamBuilder<List<TradingPair>>(
      stream: repository.tradingPairsStream(),
      builder: (context, pairSnapshot) {
        if (pairSnapshot.hasError) {
          return _ErrorState(
            error: pairSnapshot.error,
            onRetry: repository.refreshTradingData,
          );
        }

        final pairs = pairSnapshot.data ?? const <TradingPair>[];

        return StreamBuilder<TradingDataDisplayMode>(
          stream: repository.tradingDataDisplayModeFor(user.uid),
          builder: (context, modeSnapshot) {
            if (modeSnapshot.hasError) {
              return _ErrorState(
                error: modeSnapshot.error,
                onRetry: repository.refreshTradingData,
              );
            }
            final selectedMode =
                modeSnapshot.data ?? TradingDataDisplayMode.manualOnly;
            final mode = PermissionService(user).can(AppPermission.mt5SyncOwn)
                ? selectedMode
                : TradingDataDisplayMode.manualOnly;
            if (mode == TradingDataDisplayMode.mt5Only) {
              final requestedAccountId = selectedMt5AccountId?.trim();
              if (requestedAccountId == '*') {
                return StreamBuilder<List<Mt5Account>>(
                  stream: repository.mt5AccountsFor(user.uid),
                  builder: (context, accountSnapshot) {
                    if (accountSnapshot.hasError) {
                      return _ErrorState(
                        error: accountSnapshot.error,
                        onRetry: repository.refreshTradingData,
                      );
                    }
                    if (!accountSnapshot.hasData) {
                      return const AnalyticsSkeleton();
                    }
                    final accountKeys = accountSnapshot.data!
                        .map((account) => account.id)
                        .toList(growable: false);
                    return _TradingStreams(
                      journalStream: repository.mt5JournalEntriesForAccounts(
                        user.uid,
                        accountKeys: accountKeys,
                      ),
                      capitalStream: repository
                          .mt5CapitalTransactionsForAccounts(
                            user.uid,
                            accountKeys: accountKeys,
                          ),
                      pairs: pairs,
                      mode: mode,
                      builder: builder,
                    );
                  },
                );
              }
              if (requestedAccountId != null && requestedAccountId.isNotEmpty) {
                return _TradingStreams(
                  journalStream: repository.mt5JournalEntriesFor(
                    user.uid,
                    accountKey: requestedAccountId,
                  ),
                  capitalStream: repository.mt5CapitalTransactionsFor(
                    user.uid,
                    accountKey: requestedAccountId,
                  ),
                  pairs: pairs,
                  mode: mode,
                  builder: builder,
                );
              }
              return StreamBuilder<Mt5Account?>(
                stream: repository.mainMt5AccountFor(user.uid),
                builder: (context, accountSnapshot) {
                  if (accountSnapshot.hasError) {
                    return _ErrorState(
                      error: accountSnapshot.error,
                      onRetry: repository.refreshTradingData,
                    );
                  }
                  if (!accountSnapshot.hasData &&
                      accountSnapshot.connectionState ==
                          ConnectionState.waiting) {
                    return const AnalyticsSkeleton();
                  }
                  final account = accountSnapshot.data;
                  final journalStream = account == null
                      ? Stream<List<TradingJournalEntry>>.value(const [])
                      : repository.mt5JournalEntriesFor(
                          user.uid,
                          accountKey: account.id,
                        );
                  return _TradingStreams(
                    journalStream: journalStream,
                    capitalStream: account == null
                        ? Stream<List<CapitalTransaction>>.value(const [])
                        : repository.mt5CapitalTransactionsFor(
                            user.uid,
                            accountKey: account.id,
                          ),
                    pairs: pairs,
                    mode: mode,
                    builder: builder,
                  );
                },
              );
            }

            final journalStream = repository
                .journalsFor(user.uid)
                .map((journals) => filterTradesForDisplayMode(journals, mode));
            final capitalStream = repository.capitalsFor(user.uid);

            return _TradingStreams(
              journalStream: journalStream,
              capitalStream: capitalStream,
              pairs: pairs,
              mode: mode,
              builder: builder,
            );
          },
        );
      },
    );
  }
}

class _TradingStreams extends StatelessWidget {
  const _TradingStreams({
    required this.journalStream,
    required this.capitalStream,
    required this.pairs,
    required this.mode,
    required this.builder,
  });

  final Stream<List<TradingJournalEntry>> journalStream;
  final Stream<List<CapitalTransaction>> capitalStream;
  final List<TradingPair> pairs;
  final TradingDataDisplayMode mode;
  final TradingDataWidgetBuilder builder;

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<List<TradingJournalEntry>>(
      stream: journalStream,
      builder: (context, journalSnapshot) {
        if (journalSnapshot.hasError) {
          return _ErrorState(error: journalSnapshot.error);
        }

        return StreamBuilder<List<CapitalTransaction>>(
          stream: capitalStream,
          builder: (context, capitalSnapshot) {
            if (capitalSnapshot.hasError) {
              return _ErrorState(error: capitalSnapshot.error);
            }

            final hasData = journalSnapshot.hasData && capitalSnapshot.hasData;
            if (!hasData) {
              return const AnalyticsSkeleton();
            }

            return builder(
              context,
              journalSnapshot.data ?? const <TradingJournalEntry>[],
              capitalSnapshot.data ?? const <CapitalTransaction>[],
              pairs,
              mode,
            );
          },
        );
      },
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({this.error, this.onRetry});

  final Object? error;
  final Future<void> Function()? onRetry;

  @override
  Widget build(BuildContext context) {
    final message = context.l10n.text(
      AppFeedback.messageFor(
        error ?? Exception('Unable to load trading data.'),
      ),
    );

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: AnalyticsEmptyState(
          icon: Icons.cloud_off_outlined,
          title: context.l10n.text('Unable to load trading analytics'),
          message: message,
          action: onRetry == null
              ? null
              : FilledButton.tonalIcon(
                  onPressed: () => onRetry!(),
                  icon: const Icon(Icons.refresh),
                  label: Text(context.l10n.text('Retry')),
                ),
        ),
      ),
    );
  }
}
