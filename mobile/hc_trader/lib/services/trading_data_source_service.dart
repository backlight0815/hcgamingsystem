import '../models/mt5_sync_models.dart';
import '../models/trading_data_source.dart';
import '../models/trading_journal_entry.dart';

bool shouldIncludeTradeForDisplayMode(
  TradingJournalEntry trade,
  TradingDataDisplayMode mode,
) {
  return switch (mode) {
    TradingDataDisplayMode.manualOnly => trade.source.isManualDisplaySource,
    TradingDataDisplayMode.mt5Only =>
      trade.source == TradingRecordSource.mt5Sync,
  };
}

List<TradingJournalEntry> filterTradesForDisplayMode(
  Iterable<TradingJournalEntry> trades,
  TradingDataDisplayMode mode,
) {
  return trades
      .where((trade) => shouldIncludeTradeForDisplayMode(trade, mode))
      .toList(growable: false);
}

double mt5AccountCapitalBalance(Mt5Account? account) {
  if (account == null) return 0;
  if (account.isConnectionFresh) {
    return account.balance ??
        account.equity ??
        account.latestImportedClosingBalance ??
        0;
  }
  return account.latestImportedClosingBalance ??
      account.balance ??
      account.equity ??
      0;
}

TradingJournalEntry journalFromMt5Record(Mt5TradeRecord record) {
  final openedAt = record.openTime ?? record.closeTime ?? DateTime.now();
  final closedAt = record.closeTime ?? record.openTime ?? openedAt;
  final netProfit = record.netProfit;
  final isClosed = record.isClosed;
  final direction = record.tradeType.toLowerCase().contains('sell') ? 2 : 1;
  final exitPrice = record.closePrice != 0
      ? record.closePrice
      : record.currentPrice != 0
      ? record.currentPrice
      : record.entryPrice;
  final notes = [
    if (record.notes.trim().isNotEmpty) record.notes.trim(),
    if (record.comment.trim().isNotEmpty) record.comment.trim(),
  ].join('\n');
  final importedFromExcel =
      record.source.toLowerCase().contains('excel') ||
      record.source.toLowerCase().contains('import');

  return TradingJournalEntry(
    id: 'mt5:${record.accountKey}:${record.canonicalLifecycleKey}',
    userId: record.userId,
    type: 'trade',
    source: importedFromExcel
        ? TradingRecordSource.excelImport
        : TradingRecordSource.mt5Sync,
    tradingAccountId: record.accountKey,
    status: isClosed ? 'closed' : record.status,
    closeHandling: 'mt5_sync',
    timeInputTimezone: 'mt5',
    timeInputOffsetMinutes: 180,
    openDate: openedAt,
    closeDate: closedAt,
    pair: record.symbol.toUpperCase(),
    direction: direction,
    entryPrice: record.entryPrice,
    exitPrice: exitPrice,
    lotSize: record.lotSize,
    pips: 0,
    profitLoss: netProfit,
    grossProfit: record.profit,
    commission: record.commission,
    swap: record.swap,
    fees: record.fee,
    stopLoss: record.sl == 0 ? null : record.sl,
    takeProfit: record.tp == 0 ? null : record.tp,
    setupName: record.setup.trim().isEmpty ? null : record.setup.trim(),
    sourceTicket: record.canonicalDealTicket,
    sourceOrderId: record.canonicalOrderTicket,
    sourcePositionId: record.canonicalPositionTicket,
    result: !isClosed
        ? 0
        : netProfit < 0
        ? 2
        : netProfit == 0
        ? 3
        : 1,
    notes: notes,
    confidenceLevel: record.confidence,
    disciplineRating: record.discipline,
    mistakeTags: record.customTags,
    psychologyNotes: record.mistakeReason ?? '',
    followedTradingPlan: record.followedTradingPlan ?? false,
    tradingPlanId: record.tradingPlanId,
    tradingPlanName: record.tradingPlanName,
    strategyId: record.strategyId,
    createdAt: record.syncedAt,
    updatedAt: record.lastSeenAt ?? record.syncedAt,
  );
}

String tradingDataEmptyTitle(TradingDataDisplayMode mode) {
  return switch (mode) {
    TradingDataDisplayMode.manualOnly => 'No manual trading data found.',
    TradingDataDisplayMode.mt5Only => 'No MT5 synchronized trading data found.',
  };
}
