import '../../../models/capital_transaction.dart';
import '../../../models/trading_data_source.dart';
import '../../../models/trading_journal_entry.dart';
import '../domain/analytics_trade.dart';

class AnalyticsTradeAdapter {
  const AnalyticsTradeAdapter();

  AnalyticsTrade fromJournal(
    TradingJournalEntry journal, {
    String accountCurrency = '',
  }) {
    final costs = journal.commission + journal.swap + journal.fees;
    final gross = journal.grossProfit ?? journal.profitLoss - costs;
    return AnalyticsTrade(
      id: journal.id,
      userId: journal.userId,
      accountId: _nullable(journal.tradingAccountId),
      accountCurrency: journal.currency.trim().isNotEmpty
          ? journal.currency.trim().toUpperCase()
          : accountCurrency.trim().toUpperCase(),
      symbol: journal.pair.trim().toUpperCase(),
      direction: switch (journal.direction) {
        1 => AnalyticsTradeDirection.buy,
        2 => AnalyticsTradeDirection.sell,
        _ => AnalyticsTradeDirection.unknown,
      },
      status: _status(journal.status),
      openTimeUtc: journal.openDate.toUtc(),
      closeTimeUtc: _hasCloseTime(journal.status)
          ? journal.closeDate.toUtc()
          : null,
      entryPrice: journal.entryPrice,
      exitPrice: _hasCloseTime(journal.status) ? journal.exitPrice : null,
      lots: journal.lotSize,
      grossProfit: gross,
      commission: journal.commission,
      swap: journal.swap,
      fees: journal.fees,
      netProfit: journal.profitLoss,
      stopLoss: journal.stopLoss,
      takeProfit: journal.takeProfit,
      initialRiskAmount: journal.initialRiskAmount,
      initialRiskPercent: journal.initialRiskPercent,
      strategyId: _nullable(journal.strategyId),
      strategyName: _nullable(journal.strategyName),
      setupName: _nullable(journal.setupName),
      session: _nullable(journal.session),
      notes: _nullable(journal.notes),
      source: switch (journal.source) {
        TradingRecordSource.manual => AnalyticsTradeDataSource.manual,
        TradingRecordSource.excelImport => AnalyticsTradeDataSource.excel,
        TradingRecordSource.mt5Sync => AnalyticsTradeDataSource.mt5,
      },
      sourceTicket: _nullable(journal.sourceTicket),
      sourceOrderId: _nullable(journal.sourceOrderId),
      sourcePositionId: _nullable(journal.sourcePositionId),
      updatedAtUtc: journal.updatedAt?.toUtc(),
    );
  }

  List<AnalyticsTrade> fromJournals(
    Iterable<TradingJournalEntry> journals, {
    Map<String, String> currencyByAccount = const {},
  }) {
    return [
      for (final journal in journals)
        fromJournal(
          journal,
          accountCurrency:
              currencyByAccount[journal.tradingAccountId?.trim() ?? ''] ?? '',
        ),
    ];
  }

  AnalyticsCashFlow fromCapital(
    CapitalTransaction capital, {
    String? accountId,
    String currency = '',
    AnalyticsTradeDataSource source = AnalyticsTradeDataSource.manual,
  }) {
    final parsedAccount = accountId ?? _accountFromCapitalId(capital.id);
    return AnalyticsCashFlow(
      id: capital.id,
      userId: capital.userId,
      accountId: parsedAccount,
      currency: currency.trim().toUpperCase(),
      timestampUtc: capital.depositDate.toUtc(),
      amount: capital.amount.abs(),
      type: capital.type == 2
          ? AnalyticsCashFlowType.withdrawal
          : AnalyticsCashFlowType.deposit,
      source: source,
    );
  }

  List<AnalyticsCashFlow> fromCapitals(
    Iterable<CapitalTransaction> capitals, {
    Map<String, String> currencyByAccount = const {},
  }) {
    return [
      for (final capital in capitals)
        () {
          final accountId =
              _nullable(capital.tradingAccountId) ??
              _accountFromCapitalId(capital.id);
          return fromCapital(
            capital,
            accountId: accountId,
            currency: currencyByAccount[accountId ?? ''] ?? '',
            source: capital.id.startsWith('mt5:')
                ? AnalyticsTradeDataSource.mt5
                : AnalyticsTradeDataSource.manual,
          );
        }(),
    ];
  }
}

AnalyticsTradeStatus _status(String value) {
  final normalized = value.trim().toLowerCase().replaceAll(
    RegExp(r'[\s-]+'),
    '_',
  );
  return switch (normalized) {
    'closed' || 'triggered' || 'completed' => AnalyticsTradeStatus.closed,
    'open' || 'active' || 'partially_filled' => AnalyticsTradeStatus.open,
    'pending' || 'draft' => AnalyticsTradeStatus.pending,
    'cancelled' || 'canceled' || 'deleted' => AnalyticsTradeStatus.cancelled,
    'rejected' || 'invalid' => AnalyticsTradeStatus.rejected,
    'expired' => AnalyticsTradeStatus.expired,
    _ => AnalyticsTradeStatus.unknown,
  };
}

bool _hasCloseTime(String status) =>
    _status(status) == AnalyticsTradeStatus.closed;

String? _nullable(String? value) {
  final normalized = value?.trim() ?? '';
  return normalized.isEmpty ? null : normalized;
}

String? _accountFromCapitalId(String id) {
  if (!id.startsWith('mt5:')) return null;
  final pieces = id.split(':');
  return pieces.length >= 3 && pieces[1].trim().isNotEmpty ? pieces[1] : null;
}
