import 'package:flutter/foundation.dart';

import '../models/mt5_sync_models.dart';

enum Mt5TradeExclusionReason {
  none,
  accountScopeMismatch,
  activePendingOrder,
  cancelledPendingWithoutExecution,
  expiredPendingWithoutExecution,
  rejectedPendingWithoutExecution,
  untriggeredPendingOrder,
  orderLifecycleDuplicate,
  balanceOperation,
  supersededDuplicate,
  missingExecutionEvidence,
  unsupportedRecord,
}

@immutable
class TradeEligibilityResult {
  const TradeEligibilityResult({
    required this.isExecutedTrade,
    required this.includeInClosedTradeStatistics,
    required this.includeInOpenTradeStatistics,
    required this.includeInBehaviourScoring,
    required this.includeInPdfReport,
    required this.exclusionReason,
  });

  final bool isExecutedTrade;
  final bool includeInClosedTradeStatistics;
  final bool includeInOpenTradeStatistics;
  final bool includeInBehaviourScoring;
  final bool includeInPdfReport;
  final Mt5TradeExclusionReason exclusionReason;

  static const excluded = TradeEligibilityResult(
    isExecutedTrade: false,
    includeInClosedTradeStatistics: false,
    includeInOpenTradeStatistics: false,
    includeInBehaviourScoring: false,
    includeInPdfReport: false,
    exclusionReason: Mt5TradeExclusionReason.unsupportedRecord,
  );
}

@immutable
class ExcludedMt5Record {
  const ExcludedMt5Record({required this.record, required this.reason});

  final Mt5TradeRecord record;
  final Mt5TradeExclusionReason reason;
}

@immutable
class Mt5TradeAnalyticsDiagnostics {
  const Mt5TradeAnalyticsDiagnostics({
    required this.rawRecords,
    required this.scopedRecords,
    required this.executedClosedTrades,
    required this.executedOpenTrades,
    required this.activePendingOrders,
    required this.cancelledUntriggeredOrders,
    required this.expiredUntriggeredOrders,
    required this.rejectedUntriggeredOrders,
    required this.duplicatesRemoved,
    required this.balanceOperationsExcluded,
    required this.ambiguousRecordsExcluded,
  });

  final int rawRecords;
  final int scopedRecords;
  final int executedClosedTrades;
  final int executedOpenTrades;
  final int activePendingOrders;
  final int cancelledUntriggeredOrders;
  final int expiredUntriggeredOrders;
  final int rejectedUntriggeredOrders;
  final int duplicatesRemoved;
  final int balanceOperationsExcluded;
  final int ambiguousRecordsExcluded;

  Map<String, int> toJson() => {
    'raw_records': rawRecords,
    'scoped_records': scopedRecords,
    'executed_closed_trades': executedClosedTrades,
    'executed_open_trades': executedOpenTrades,
    'active_pending_orders': activePendingOrders,
    'cancelled_untriggered_orders': cancelledUntriggeredOrders,
    'expired_untriggered_orders': expiredUntriggeredOrders,
    'rejected_untriggered_orders': rejectedUntriggeredOrders,
    'duplicates_removed': duplicatesRemoved,
    'balance_operations_excluded': balanceOperationsExcluded,
    'ambiguous_records_excluded': ambiguousRecordsExcluded,
  };
}

@immutable
class Mt5TradeAnalyticsDataset {
  const Mt5TradeAnalyticsDataset({
    required this.executedClosedTrades,
    required this.executedOpenTrades,
    required this.activePendingOrders,
    required this.cancelledUntriggeredOrders,
    required this.expiredUntriggeredOrders,
    required this.rejectedUntriggeredOrders,
    required this.excludedNonTradeRecords,
    required this.diagnostics,
  });

  final List<Mt5TradeRecord> executedClosedTrades;
  final List<Mt5TradeRecord> executedOpenTrades;
  final List<Mt5TradeRecord> activePendingOrders;
  final List<Mt5TradeRecord> cancelledUntriggeredOrders;
  final List<Mt5TradeRecord> expiredUntriggeredOrders;
  final List<Mt5TradeRecord> rejectedUntriggeredOrders;
  final List<ExcludedMt5Record> excludedNonTradeRecords;
  final Mt5TradeAnalyticsDiagnostics diagnostics;

  List<Mt5TradeRecord> get executedTrades =>
      List.unmodifiable([...executedOpenTrades, ...executedClosedTrades]);
}

class Mt5TradeEligibilityService {
  const Mt5TradeEligibilityService();

  TradeEligibilityResult evaluate(Mt5TradeRecord record) {
    final recordType = _normalize(record.recordType);
    final status = record.normalizedOrderState;

    if (record.isBalanceOperation) {
      return _excluded(Mt5TradeExclusionReason.balanceOperation);
    }
    if (recordType == 'order') {
      if (status == 'pending') {
        return _excluded(Mt5TradeExclusionReason.activePendingOrder);
      }
      if (status == 'cancelled') {
        return _excluded(
          Mt5TradeExclusionReason.cancelledPendingWithoutExecution,
        );
      }
      if (status == 'expired') {
        return _excluded(
          Mt5TradeExclusionReason.expiredPendingWithoutExecution,
        );
      }
      if (status == 'rejected') {
        return _excluded(
          Mt5TradeExclusionReason.rejectedPendingWithoutExecution,
        );
      }
      if (record.isPendingOrderType && !record.hasExecutionEvidence) {
        return _excluded(Mt5TradeExclusionReason.untriggeredPendingOrder);
      }
      // Orders are instructions, never analytics trades. A filled or partially
      // filled order is represented once by its deal/position lifecycle.
      return _excluded(Mt5TradeExclusionReason.orderLifecycleDuplicate);
    }
    if (_normalize(record.status) == 'superseded') {
      return _excluded(Mt5TradeExclusionReason.supersededDuplicate);
    }
    if (!record.hasExecutionEvidence) {
      return _excluded(Mt5TradeExclusionReason.missingExecutionEvidence);
    }

    final isOpen = record.isOpen;
    final isClosed =
        record.isClosed ||
        (!isOpen && record.closeTime != null && record.recordType == 'deal');
    if (!isOpen && !isClosed) {
      return _excluded(Mt5TradeExclusionReason.unsupportedRecord);
    }
    return TradeEligibilityResult(
      isExecutedTrade: true,
      includeInClosedTradeStatistics: isClosed,
      includeInOpenTradeStatistics: isOpen,
      includeInBehaviourScoring: isClosed,
      includeInPdfReport: isClosed,
      exclusionReason: Mt5TradeExclusionReason.none,
    );
  }

  Mt5TradeAnalyticsDataset build(
    Iterable<Mt5TradeRecord> records, {
    required String userId,
    required String accountKey,
  }) {
    final raw = records.toList(growable: false);
    final scoped = raw
        .where(
          (record) =>
              record.userId == userId && record.accountKey == accountKey,
        )
        .toList(growable: false);
    final canonical = dedupeMt5LifecycleRecords(scoped);
    final closed = <Mt5TradeRecord>[];
    final open = <Mt5TradeRecord>[];
    final pending = <Mt5TradeRecord>[];
    final cancelled = <Mt5TradeRecord>[];
    final expired = <Mt5TradeRecord>[];
    final rejected = <Mt5TradeRecord>[];
    final excluded = <ExcludedMt5Record>[];
    var balanceOperations = 0;
    var ambiguous = 0;

    for (final record in canonical) {
      final result = evaluate(record);
      if (result.includeInClosedTradeStatistics) {
        closed.add(record);
      } else if (result.includeInOpenTradeStatistics) {
        open.add(record);
      } else {
        excluded.add(
          ExcludedMt5Record(record: record, reason: result.exclusionReason),
        );
        switch (result.exclusionReason) {
          case Mt5TradeExclusionReason.activePendingOrder:
            pending.add(record);
          case Mt5TradeExclusionReason.cancelledPendingWithoutExecution:
            cancelled.add(record);
          case Mt5TradeExclusionReason.expiredPendingWithoutExecution:
            expired.add(record);
          case Mt5TradeExclusionReason.rejectedPendingWithoutExecution:
            rejected.add(record);
          case Mt5TradeExclusionReason.balanceOperation:
            balanceOperations++;
          case Mt5TradeExclusionReason.missingExecutionEvidence:
          case Mt5TradeExclusionReason.unsupportedRecord:
          case Mt5TradeExclusionReason.untriggeredPendingOrder:
            ambiguous++;
          default:
            break;
        }
      }
    }

    closed.sort(_oldestFirst);
    open.sort(_oldestFirst);
    return Mt5TradeAnalyticsDataset(
      executedClosedTrades: List.unmodifiable(closed),
      executedOpenTrades: List.unmodifiable(open),
      activePendingOrders: List.unmodifiable(pending),
      cancelledUntriggeredOrders: List.unmodifiable(cancelled),
      expiredUntriggeredOrders: List.unmodifiable(expired),
      rejectedUntriggeredOrders: List.unmodifiable(rejected),
      excludedNonTradeRecords: List.unmodifiable(excluded),
      diagnostics: Mt5TradeAnalyticsDiagnostics(
        rawRecords: raw.length,
        scopedRecords: scoped.length,
        executedClosedTrades: closed.length,
        executedOpenTrades: open.length,
        activePendingOrders: pending.length,
        cancelledUntriggeredOrders: cancelled.length,
        expiredUntriggeredOrders: expired.length,
        rejectedUntriggeredOrders: rejected.length,
        duplicatesRemoved: scoped.length - canonical.length,
        balanceOperationsExcluded: balanceOperations,
        ambiguousRecordsExcluded: ambiguous,
      ),
    );
  }

  TradeEligibilityResult _excluded(Mt5TradeExclusionReason reason) {
    return TradeEligibilityResult(
      isExecutedTrade: false,
      includeInClosedTradeStatistics: false,
      includeInOpenTradeStatistics: false,
      includeInBehaviourScoring: false,
      includeInPdfReport: false,
      exclusionReason: reason,
    );
  }

  int _oldestFirst(Mt5TradeRecord left, Mt5TradeRecord right) =>
      mt5LifecycleSortTime(left).compareTo(mt5LifecycleSortTime(right));

  String _normalize(String value) =>
      value.trim().toLowerCase().replaceAll(RegExp(r'[\s-]+'), '_');
}
