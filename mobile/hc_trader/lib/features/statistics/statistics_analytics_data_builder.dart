import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/security/permission_service.dart';
import '../../models/app_user.dart';
import '../../models/capital_transaction.dart';
import '../../models/mt5_sync_models.dart';
import '../../models/trading_account.dart';
import '../../models/trading_journal_entry.dart';
import '../../repositories/trading_repository.dart';
import '../../services/trading_data_source_service.dart';
import '../../shared/analytics/analytics_widgets.dart';
import '../trading_analytics/data/analytics_trade_adapter.dart';
import '../trading_analytics/domain/analytics_filter.dart';
import '../trading_analytics/domain/analytics_trade.dart';
import '../trading_analytics/presentation/analytics_filter_bar.dart';

class StatisticsAnalyticsBundle {
  const StatisticsAnalyticsBundle({
    required this.trades,
    required this.cashFlows,
    required this.accounts,
    required this.currencyByAccount,
    required this.canUseMt5,
    required this.isGuest,
    this.rawJournals = const [],
    this.rawCapitals = const [],
    this.mt5Accounts = const [],
    this.configuredStartingBalance,
    this.liveCurrentBalance,
    this.liveCurrentEquity,
    this.lastRefreshUtc,
  });

  final List<AnalyticsTrade> trades;
  final List<AnalyticsCashFlow> cashFlows;
  final List<AnalyticsAccountOption> accounts;
  final Map<String, String> currencyByAccount;
  final bool canUseMt5;
  final bool isGuest;
  final List<TradingJournalEntry> rawJournals;
  final List<CapitalTransaction> rawCapitals;
  final List<Mt5Account> mt5Accounts;
  final double? configuredStartingBalance;
  final double? liveCurrentBalance;
  final double? liveCurrentEquity;
  final DateTime? lastRefreshUtc;
}

typedef StatisticsAnalyticsBundleBuilder =
    Widget Function(BuildContext context, StatisticsAnalyticsBundle bundle);

class StatisticsAnalyticsDataBuilder extends StatelessWidget {
  const StatisticsAnalyticsDataBuilder({
    super.key,
    required this.user,
    required this.repository,
    required this.filter,
    required this.builder,
  });

  final AppUser user;
  final TradingRepository repository;
  final AnalyticsFilter filter;
  final StatisticsAnalyticsBundleBuilder builder;

  @override
  Widget build(BuildContext context) {
    if (user.isDemo) return builder(context, _demoBundle());
    final canUseMt5 = PermissionService(user).can(AppPermission.mt5SyncOwn);
    return StreamBuilder<List<TradingJournalEntry>>(
      stream: repository.journalsFor(user.uid),
      builder: (context, journalSnapshot) {
        if (journalSnapshot.hasError) {
          return _ErrorState(onRetry: repository.refreshTradingData);
        }
        return StreamBuilder<List<CapitalTransaction>>(
          stream: repository.capitalsFor(user.uid),
          builder: (context, capitalSnapshot) {
            if (capitalSnapshot.hasError) {
              return _ErrorState(onRetry: repository.refreshTradingData);
            }
            if (!journalSnapshot.hasData || !capitalSnapshot.hasData) {
              return const AnalyticsSkeleton();
            }
            if (!canUseMt5) {
              return _buildBundle(
                context,
                manualJournals: journalSnapshot.data!,
                manualCapitals: capitalSnapshot.data!,
                manualAccounts: const [],
                mt5Accounts: const [],
                mt5Journals: const [],
                mt5Capitals: const [],
                canUseMt5: false,
              );
            }
            return StreamBuilder<List<TradingAccount>>(
              stream: repository.tradingAccountsFor(user.uid),
              builder: (context, manualAccountSnapshot) {
                if (manualAccountSnapshot.hasError) {
                  return _ErrorState(onRetry: repository.refreshTradingData);
                }
                return StreamBuilder<List<Mt5Account>>(
                  stream: repository.mt5AccountsFor(user.uid),
                  builder: (context, mt5AccountSnapshot) {
                    if (mt5AccountSnapshot.hasError) {
                      return _ErrorState(
                        onRetry: repository.refreshTradingData,
                      );
                    }
                    if (!manualAccountSnapshot.hasData ||
                        !mt5AccountSnapshot.hasData) {
                      return const AnalyticsSkeleton();
                    }
                    final mt5Accounts = mt5AccountSnapshot.data!;
                    final keys = mt5Accounts
                        .map((account) => account.id)
                        .where((id) => id.trim().isNotEmpty)
                        .toList(growable: false);
                    final mt5JournalStream = keys.isEmpty
                        ? Stream<List<TradingJournalEntry>>.value(const [])
                        : repository.mt5JournalEntriesForAccounts(
                            user.uid,
                            accountKeys: keys,
                            limitPerAccount: 10000,
                          );
                    final mt5CapitalStream = keys.isEmpty
                        ? Stream<List<CapitalTransaction>>.value(const [])
                        : repository.mt5CapitalTransactionsForAccounts(
                            user.uid,
                            accountKeys: keys,
                          );
                    return StreamBuilder<List<TradingJournalEntry>>(
                      stream: mt5JournalStream,
                      builder: (context, mt5JournalSnapshot) {
                        if (mt5JournalSnapshot.hasError) {
                          return _ErrorState(
                            onRetry: repository.refreshTradingData,
                          );
                        }
                        return StreamBuilder<List<CapitalTransaction>>(
                          stream: mt5CapitalStream,
                          builder: (context, mt5CapitalSnapshot) {
                            if (mt5CapitalSnapshot.hasError) {
                              return _ErrorState(
                                onRetry: repository.refreshTradingData,
                              );
                            }
                            if (!mt5JournalSnapshot.hasData ||
                                !mt5CapitalSnapshot.hasData) {
                              return const AnalyticsSkeleton();
                            }
                            return _buildBundle(
                              context,
                              manualJournals: journalSnapshot.data!,
                              manualCapitals: capitalSnapshot.data!,
                              manualAccounts: manualAccountSnapshot.data!,
                              mt5Accounts: mt5Accounts,
                              mt5Journals: mt5JournalSnapshot.data!,
                              mt5Capitals: mt5CapitalSnapshot.data!,
                              canUseMt5: true,
                            );
                          },
                        );
                      },
                    );
                  },
                );
              },
            );
          },
        );
      },
    );
  }

  Widget _buildBundle(
    BuildContext context, {
    required List<TradingJournalEntry> manualJournals,
    required List<CapitalTransaction> manualCapitals,
    required List<TradingAccount> manualAccounts,
    required List<Mt5Account> mt5Accounts,
    required List<TradingJournalEntry> mt5Journals,
    required List<CapitalTransaction> mt5Capitals,
    required bool canUseMt5,
  }) {
    const adapter = AnalyticsTradeAdapter();
    final currencyByAccount = <String, String>{
      for (final account in manualAccounts)
        account.id: account.currency.toUpperCase(),
      for (final account in mt5Accounts)
        account.id: account.currency.toUpperCase(),
    };
    final trades = adapter.fromJournals([
      ...manualJournals,
      ...mt5Journals,
    ], currencyByAccount: currencyByAccount);
    final cashFlows = adapter.fromCapitals([
      ...manualCapitals,
      ...mt5Capitals,
    ], currencyByAccount: currencyByAccount);
    final accountOptions = <AnalyticsAccountOption>[
      for (final account in manualAccounts)
        AnalyticsAccountOption(
          id: account.id,
          label: account.displayName,
          currency: account.currency,
          source: AnalyticsTradeDataSource.manual,
          isMain: account.isPrimary,
        ),
      for (final account in mt5Accounts)
        if (!manualAccounts.any((manual) => manual.id == account.id))
          AnalyticsAccountOption(
            id: account.id,
            label: account.label,
            currency: account.currency,
            source: AnalyticsTradeDataSource.mt5,
            isMain: account.isPrimary || user.mainMt5AccountKey == account.id,
          ),
    ];
    final selectedManualAccounts = manualAccounts.where(
      (account) => filter.accountId == '*' || account.id == filter.accountId,
    );
    final selectedMt5Accounts = mt5Accounts.where(
      (account) => filter.accountId == '*' || account.id == filter.accountId,
    );
    final includeManual =
        filter.sources.contains(AnalyticsTradeDataSource.manual) ||
        filter.sources.contains(AnalyticsTradeDataSource.excel);
    final includeMt5 =
        filter.sources.contains(AnalyticsTradeDataSource.mt5) ||
        filter.sources.contains(AnalyticsTradeDataSource.excel);
    final currentBalances = <double>[
      if (includeManual)
        for (final account in selectedManualAccounts)
          account.calculatedBalance != 0
              ? account.calculatedBalance
              : account.startingBalance,
      if (includeMt5)
        for (final account in selectedMt5Accounts)
          mt5AccountCapitalBalance(account),
    ];
    final equities = <double>[
      if (includeManual)
        for (final account in selectedManualAccounts)
          account.currentEquity ??
              (account.calculatedBalance != 0
                  ? account.calculatedBalance
                  : account.startingBalance),
      if (includeMt5)
        for (final account in selectedMt5Accounts)
          account.equity ?? mt5AccountCapitalBalance(account),
    ];
    final startingBalances = <double>[
      if (includeManual)
        for (final account in selectedManualAccounts) account.startingBalance,
    ];
    final refreshTimes = [
      for (final account in selectedMt5Accounts)
        account.lastSuccessfulSyncAt ??
            account.lastSyncTime ??
            account.updatedAt,
    ].whereType<DateTime>().toList()..sort();
    return builder(
      context,
      StatisticsAnalyticsBundle(
        trades: trades,
        cashFlows: cashFlows,
        accounts: accountOptions,
        currencyByAccount: currencyByAccount,
        canUseMt5: canUseMt5,
        isGuest: false,
        rawJournals: [...manualJournals, ...mt5Journals],
        rawCapitals: [...manualCapitals, ...mt5Capitals],
        mt5Accounts: mt5Accounts,
        configuredStartingBalance: startingBalances.isEmpty
            ? null
            : startingBalances.reduce((a, b) => a + b),
        liveCurrentBalance: currentBalances.isEmpty
            ? null
            : currentBalances.reduce((a, b) => a + b),
        liveCurrentEquity: equities.isEmpty
            ? null
            : equities.reduce((a, b) => a + b),
        lastRefreshUtc: refreshTimes.lastOrNull?.toUtc(),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.onRetry});

  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: AnalyticsEmptyState(
          icon: Icons.cloud_off_outlined,
          title: context.l10n.text('Unable to load trading analytics'),
          message: context.l10n.text(
            'Your private trading records could not be loaded. Check the connection and try again.',
          ),
          action: FilledButton.tonalIcon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
            label: Text(context.l10n.text('Retry')),
          ),
        ),
      ),
    );
  }
}

StatisticsAnalyticsBundle _demoBundle() {
  final now = DateTime.utc(2026, 7, 28, 12);
  const accounts = [
    AnalyticsAccountOption(
      id: 'demo-manual',
      label: 'Demo Manual Account',
      currency: 'USD',
      source: AnalyticsTradeDataSource.manual,
      isMain: true,
    ),
    AnalyticsAccountOption(
      id: 'demo-excel',
      label: 'Demo Excel Import',
      currency: 'USD',
      source: AnalyticsTradeDataSource.excel,
    ),
    AnalyticsAccountOption(
      id: 'demo-mt5',
      label: 'Demo MT5 Account',
      currency: 'USD',
      source: AnalyticsTradeDataSource.mt5,
    ),
  ];
  final trades = <AnalyticsTrade>[
    for (var index = 0; index < 36; index++)
      () {
        final source = AnalyticsTradeDataSource.values[index % 3];
        final opened = now.subtract(Duration(days: 72 - index * 2, hours: 4));
        final result = index % 7 == 0
            ? -135.0 - index
            : index % 5 == 0
            ? 0.0
            : 65.0 + index * 3;
        final cost = index % 3 == 0 ? -4.5 : -2.0;
        return AnalyticsTrade(
          id: 'demo-$index',
          userId: 'guest-analytics',
          accountId: switch (source) {
            AnalyticsTradeDataSource.manual => 'demo-manual',
            AnalyticsTradeDataSource.excel => 'demo-excel',
            AnalyticsTradeDataSource.mt5 => 'demo-mt5',
          },
          accountCurrency: 'USD',
          symbol: const ['XAUUSD', 'EURUSD', 'GBPUSD', 'USDJPY'][index % 4],
          direction: index.isEven
              ? AnalyticsTradeDirection.buy
              : AnalyticsTradeDirection.sell,
          status: AnalyticsTradeStatus.closed,
          openTimeUtc: opened,
          closeTimeUtc: opened.add(Duration(minutes: 20 + index * index * 3)),
          entryPrice: 1 + index / 100,
          exitPrice: 1.01 + index / 100,
          lots: .1 + (index % 6) * .05,
          grossProfit: result - cost,
          commission: cost,
          swap: 0,
          fees: 0,
          netProfit: result,
          stopLoss: 0.98,
          takeProfit: 1.03,
          initialRiskAmount: 80 + (index % 4) * 10,
          initialRiskPercent: 1,
          strategyId: 'strategy-${index % 3}',
          strategyName: const ['Breakout', 'Pullback', 'Range'][index % 3],
          setupName: const ['A+', 'Trend', 'Reversal'][index % 3],
          session: const ['Asia', 'London', 'New York'][index % 3],
          source: source,
          sourceTicket: source == AnalyticsTradeDataSource.manual
              ? null
              : '${800000 + index}',
          sourcePositionId: source == AnalyticsTradeDataSource.mt5
              ? '${700000 + index}'
              : null,
          updatedAtUtc: opened.add(const Duration(hours: 8)),
        );
      }(),
    AnalyticsTrade(
      id: 'demo-open',
      userId: 'guest-analytics',
      accountId: 'demo-mt5',
      accountCurrency: 'USD',
      symbol: 'XAUUSD',
      direction: AnalyticsTradeDirection.buy,
      status: AnalyticsTradeStatus.open,
      openTimeUtc: now.subtract(const Duration(hours: 3)),
      entryPrice: 2350,
      exitPrice: 2353,
      lots: .2,
      grossProfit: 42,
      commission: -2,
      swap: 0,
      fees: 0,
      netProfit: 40,
      strategyName: 'Breakout',
      setupName: 'A+',
      session: 'New York',
      source: AnalyticsTradeDataSource.mt5,
      sourcePositionId: 'demo-open-position',
      updatedAtUtc: now,
    ),
    AnalyticsTrade(
      id: 'demo-cancelled',
      userId: 'guest-analytics',
      accountId: 'demo-mt5',
      accountCurrency: 'USD',
      symbol: 'EURUSD',
      direction: AnalyticsTradeDirection.sell,
      status: AnalyticsTradeStatus.cancelled,
      openTimeUtc: now.subtract(const Duration(days: 1)),
      entryPrice: 1.08,
      lots: .1,
      grossProfit: 0,
      commission: 0,
      swap: 0,
      fees: 0,
      netProfit: 0,
      source: AnalyticsTradeDataSource.mt5,
      sourceOrderId: 'cancelled-demo',
    ),
  ];
  return StatisticsAnalyticsBundle(
    trades: trades,
    cashFlows: [
      AnalyticsCashFlow(
        id: 'demo-deposit',
        userId: 'guest-analytics',
        accountId: 'demo-manual',
        currency: 'USD',
        timestampUtc: now.subtract(const Duration(days: 100)),
        amount: 10000,
        type: AnalyticsCashFlowType.deposit,
      ),
      AnalyticsCashFlow(
        id: 'demo-withdrawal',
        userId: 'guest-analytics',
        accountId: 'demo-manual',
        currency: 'USD',
        timestampUtc: now.subtract(const Duration(days: 10)),
        amount: 500,
        type: AnalyticsCashFlowType.withdrawal,
      ),
    ],
    accounts: accounts,
    currencyByAccount: const {
      'demo-manual': 'USD',
      'demo-excel': 'USD',
      'demo-mt5': 'USD',
    },
    canUseMt5: true,
    isGuest: true,
    configuredStartingBalance: 10000,
    liveCurrentBalance: null,
    liveCurrentEquity: null,
    lastRefreshUtc: now,
  );
}
