import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/models/mt5_sync_models.dart';
import 'package:hc_trader/models/trading_report.dart';
import 'package:hc_trader/services/mt5_trade_eligibility_service.dart';
import 'package:hc_trader/services/trading_data_source_service.dart';
import 'package:hc_trader/services/trading_report_pdf_service.dart';
import 'package:hc_trader/services/trading_report_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const service = Mt5TradeEligibilityService();

  group('canonical MT5 execution eligibility', () {
    test(
      'cancelled, expired, rejected, and active pending orders are excluded',
      () {
        final records = [
          _order('cancelled', state: 'cancelled', rawType: 2),
          _order('expired', state: 'expired', rawType: 5),
          _order('rejected', state: 'rejected', rawType: 4),
          _order('invalid', state: 'invalid', rawType: 4),
          _order('pending', state: 'pending', rawType: 3),
          _order(
            'legacy-raw-expired',
            state: 'unknown',
            rawType: 5,
            rawState: 6,
          ),
        ];

        final dataset = service.build(
          records,
          userId: 'user-a',
          accountKey: 'account-a',
        );

        expect(dataset.executedTrades, isEmpty);
        expect(dataset.cancelledUntriggeredOrders, hasLength(1));
        expect(dataset.expiredUntriggeredOrders, hasLength(2));
        expect(dataset.rejectedUntriggeredOrders, hasLength(2));
        expect(dataset.activePendingOrders, hasLength(1));
      },
    );

    test(
      'triggered pending order and its position produce one canonical trade',
      () {
        final opened = DateTime.utc(2026, 7, 1, 10, 30);
        final dataset = service.build(
          [
            _order(
              'pending-source',
              state: 'triggered',
              rawType: 2,
              positionId: 7001,
              opened: DateTime.utc(2026, 7, 1, 10),
              closed: opened,
              executedVolume: .4,
            ),
            _position(
              'position-7001',
              positionId: 7001,
              opened: opened,
              closed: DateTime.utc(2026, 7, 1, 11, 15),
              volume: .4,
              profit: 12,
            ),
          ],
          userId: 'user-a',
          accountKey: 'account-a',
        );

        expect(dataset.executedClosedTrades, hasLength(1));
        expect(dataset.executedClosedTrades.single.recordType, 'position');
        expect(dataset.executedClosedTrades.single.openTime, opened);
        expect(dataset.executedClosedTrades.single.lotSize, .4);
      },
    );

    test(
      'legacy entry and exit deals aggregate into one position lifecycle',
      () {
        final opened = DateTime.utc(2026, 7, 1, 9);
        final closed = DateTime.utc(2026, 7, 1, 10);
        final dataset = service.build(
          [
            _lifecycleDeal(
              'entry-deal',
              positionId: 7100,
              dealEntry: 0,
              direction: 'buy',
              time: opened,
              profit: 0,
            ),
            _lifecycleDeal(
              'exit-deal',
              positionId: 7100,
              dealEntry: 1,
              direction: 'sell',
              time: closed,
              profit: 14,
              commission: -1,
            ),
          ],
          userId: 'user-a',
          accountKey: 'account-a',
        );

        expect(dataset.executedClosedTrades, hasLength(1));
        final trade = dataset.executedClosedTrades.single;
        expect(trade.recordType, 'position');
        expect(trade.tradeType, 'buy');
        expect(trade.openTime, opened);
        expect(trade.closeTime, closed);
        expect(trade.netProfit, 13);
        expect(trade.lotSize, .4);
      },
    );

    test('partially filled order includes only its executed position', () {
      final dataset = service.build(
        [
          _order(
            'partial-order',
            state: 'cancelled',
            rawType: 2,
            positionId: 7002,
            executedVolume: .4,
            requestedVolume: 1,
            remainingVolume: .6,
          ),
          _position(
            'partial-position',
            positionId: 7002,
            volume: .4,
            profit: 5,
          ),
        ],
        userId: 'user-a',
        accountKey: 'account-a',
      );

      expect(dataset.executedClosedTrades, hasLength(1));
      expect(dataset.executedClosedTrades.single.lotSize, .4);
    });

    test(
      'genuine break-even and zero-second executed positions remain included',
      () {
        final instant = DateTime.utc(2026, 7, 2, 12);
        final dataset = service.build(
          [
            _position(
              'break-even',
              positionId: 8001,
              profit: 0,
              opened: instant,
              closed: instant,
            ),
          ],
          userId: 'user-a',
          accountKey: 'account-a',
        );

        expect(dataset.executedClosedTrades, hasLength(1));
        expect(dataset.executedClosedTrades.single.netProfit, 0);
      },
    );

    test('balance operations and records from other accounts are excluded', () {
      final dataset = service.build(
        [
          _deal('deposit', tradeType: 'balance', rawType: 2),
          _position('right', positionId: 9001),
          _position('wrong', positionId: 9002, accountKey: 'account-b'),
        ],
        userId: 'user-a',
        accountKey: 'account-a',
      );

      expect(dataset.executedClosedTrades.map((record) => record.id), [
        'right',
      ]);
      expect(dataset.diagnostics.balanceOperationsExcluded, 1);
      expect(dataset.diagnostics.scopedRecords, 2);
    });
  });

  test('cancelled-order bursts do not affect report behaviour analytics', () {
    final base = DateTime.utc(2026, 7, 6, 9);
    final dataset = service.build(
      [
        for (var index = 0; index < 10; index++)
          _order(
            'cancelled-$index',
            state: 'cancelled',
            rawType: index.isEven ? 2 : 3,
            opened: base.add(Duration(seconds: index * 20)),
            closed: base.add(const Duration(minutes: 5)),
          ),
        _position(
          'real-position',
          positionId: 9100,
          opened: base.add(const Duration(hours: 1)),
          closed: base.add(const Duration(hours: 2)),
          profit: 10,
        ),
      ],
      userId: 'user-a',
      accountKey: 'account-a',
    );
    final report = _report(dataset.executedClosedTrades);

    expect(report.summary.totalTrades, 1);
    expect(report.behaviourProfile.layering.eventCount, 0);
    expect(report.behaviourProfile.gambling.quickCount, 0);
    expect(report.behaviourProfile.overtradingDays, 0);
    expect(report.hedgingProfile.eventCount, 0);
  });

  test(
    'three overlapping executed positions retain genuine layering evidence',
    () {
      final base = DateTime.utc(2026, 7, 7, 9);
      final dataset = service.build(
        [
          for (var index = 0; index < 3; index++)
            _position(
              'layer-$index',
              positionId: 9200 + index,
              opened: base.add(Duration(minutes: index * 2)),
              closed: base.add(const Duration(hours: 1)),
              profit: 3,
            ),
        ],
        userId: 'user-a',
        accountKey: 'account-a',
      );
      final report = _report(dataset.executedClosedTrades);

      expect(report.summary.totalTrades, 3);
      expect(report.behaviourProfile.layering.eventCount, greaterThan(0));
      expect(report.behaviourProfile.maximumLayers, 3);
    },
  );

  test(
    'attached-report equivalent excludes 23 cancelled orders from PDF dataset',
    () async {
      final base = DateTime.utc(2026, 6, 29, 8);
      final raw = <Mt5TradeRecord>[
        _position(
          'executed-0',
          positionId: 10000,
          opened: DateTime.utc(2026, 6, 29, 8, 30),
          closed: DateTime.utc(2026, 6, 29, 17, 11),
          profit: -31.82,
        ),
        _position(
          'executed-1',
          positionId: 10001,
          opened: DateTime.utc(2026, 6, 30, 4, 7),
          closed: DateTime.utc(2026, 6, 30, 4, 43),
          profit: 5.05,
        ),
        _position(
          'executed-2',
          positionId: 10002,
          opened: DateTime.utc(2026, 6, 30, 4, 48),
          closed: DateTime.utc(2026, 6, 30, 4, 55),
          profit: -.41,
        ),
        _position(
          'executed-3',
          positionId: 10003,
          opened: DateTime.utc(2026, 7, 6, 15, 42),
          closed: DateTime.utc(2026, 7, 7, 15, 9),
          profit: 36.5,
        ),
        _position(
          'executed-4',
          positionId: 10004,
          opened: DateTime.utc(2026, 7, 7, 22, 15),
          closed: DateTime.utc(2026, 7, 8, 4, 5),
          profit: 12.34,
        ),
        _position(
          'executed-5',
          positionId: 10005,
          opened: DateTime.utc(2026, 7, 7, 21, 49),
          closed: DateTime.utc(2026, 7, 7, 22, 12),
          profit: -30,
        ),
        _position(
          'executed-6',
          positionId: 10006,
          opened: DateTime.utc(2026, 7, 8, 8, 7),
          closed: DateTime.utc(2026, 7, 8, 11, 32),
          profit: 53.11,
          direction: 'sell',
        ),
        _position(
          'executed-7',
          positionId: 10007,
          opened: DateTime.utc(2026, 7, 8, 4, 29),
          closed: DateTime.utc(2026, 7, 8, 11, 17),
          profit: .15,
        ),
        for (var index = 0; index < 23; index++)
          _order(
            'cancelled-order-$index',
            state: 'cancelled',
            rawType: index.isEven ? 2 : 3,
            opened: base.add(Duration(minutes: index)),
            closed: base.add(const Duration(hours: 2)),
          ),
      ];
      final dataset = service.build(
        raw,
        userId: 'user-a',
        accountKey: 'account-a',
      );
      final report = _report(dataset.executedClosedTrades);

      expect(dataset.diagnostics.rawRecords, 31);
      expect(dataset.diagnostics.executedClosedTrades, 8);
      expect(dataset.diagnostics.cancelledUntriggeredOrders, 23);
      expect(report.summary.totalTrades, 8);
      expect(report.trades.any((trade) => trade.id.contains('order')), isFalse);
      // Keep this compact diagnostic visible in CI for the attached regression.
      // It intentionally contains no account identifiers.
      // ignore: avoid_print
      print({
        'trades': report.summary.totalTrades,
        'wins': report.summary.winningTrades,
        'losses': report.summary.losingTrades,
        'break_even': report.summary.breakevenTrades,
        'net_profit': report.summary.netProfitLoss,
        'layering_events': report.behaviourProfile.layering.eventCount,
        'rapid_entries': report.behaviourProfile.gambling.quickCount,
        'overtrading_days': report.behaviourProfile.overtradingDays,
        'hedging_events': report.hedgingProfile.eventCount,
        'gambling_score': report.behaviourProfile.gambling.score,
        'behaviour_penalty': report.scoreProfile.behaviourPenaltyPoints,
        'score': report.scoreProfile.score,
        'grade': report.scoreProfile.grade,
      });

      final bytes = await TradingReportPdfService().build(report: report);
      expect(bytes.length, greaterThan(20000));
      final output = Directory('../../output/pdf');
      output.createSync(recursive: true);
      File(
        '${output.path}/trading-journal-report-mt5-eligibility-regression.pdf',
      ).writeAsBytesSync(bytes, flush: true);
    },
    timeout: const Timeout(Duration(minutes: 2)),
  );
}

TradingReport _report(List<Mt5TradeRecord> records) {
  final journals = records.map(journalFromMt5Record).toList(growable: false);
  return TradingReportService().build(
    journals: journals,
    capitals: const [],
    traderLabel: 'Eligibility Fixture',
    currentBalanceOverride: 1000,
    account: const TradingReportAccount(
      id: 'account-a',
      name: 'Eligibility Account',
      login: '100001',
      broker: 'Broker',
      server: 'Broker-Demo',
      currency: 'USD',
      balance: 1000,
      equity: 1000,
    ),
    filter: const TradingReportFilter(
      ownerUid: 'user-a',
      source: TradingReportSource.mt5,
      mainMt5AccountId: 'account-a',
    ),
  );
}

Mt5TradeRecord _position(
  String id, {
  required int positionId,
  String accountKey = 'account-a',
  String direction = 'buy',
  DateTime? opened,
  DateTime? closed,
  double volume = .1,
  double profit = 1,
}) => Mt5TradeRecord.fromJson({
  'id': id,
  'user_id': 'user-a',
  'account_key': accountKey,
  'mt5_login': accountKey == 'account-a' ? 100001 : 200002,
  'broker_server': 'Broker-Demo',
  'symbol': 'XAUUSD',
  'position_id': positionId,
  'order_ticket': positionId,
  'deal_ticket': positionId + 100000,
  'trade_type': direction,
  'record_type': 'position',
  'status': 'closed',
  'lot_size': volume,
  'executed_volume': volume,
  'has_entry_deal': true,
  'has_exit_deal': true,
  'entry_price': 2300,
  'close_price': 2301,
  'open_time': opened ?? DateTime.utc(2026, 7, 1, 9),
  'close_time': closed ?? DateTime.utc(2026, 7, 1, 10),
  'profit': profit,
});

Mt5TradeRecord _order(
  String id, {
  required String state,
  required int rawType,
  int? rawState,
  int positionId = 0,
  DateTime? opened,
  DateTime? closed,
  double requestedVolume = .1,
  double executedVolume = 0,
  double remainingVolume = .1,
}) => Mt5TradeRecord.fromJson({
  'id': id,
  'user_id': 'user-a',
  'account_key': 'account-a',
  'mt5_login': 100001,
  'broker_server': 'Broker-Demo',
  'symbol': 'XAUUSD',
  'position_id': positionId,
  'order_ticket': id.hashCode.abs(),
  'trade_type': rawType.isEven ? 'buy' : 'sell',
  'record_type': 'order',
  'status': state,
  'raw_mt5_type': rawType,
  'raw_order_state': rawState,
  'lot_size': requestedVolume,
  'requested_volume': requestedVolume,
  'executed_volume': executedVolume,
  'remaining_volume': remainingVolume,
  'open_time': opened ?? DateTime.utc(2026, 7, 1, 9),
  'close_time': closed,
  'profit': 0,
});

Mt5TradeRecord _deal(
  String id, {
  required String tradeType,
  required int rawType,
}) => Mt5TradeRecord.fromJson({
  'id': id,
  'user_id': 'user-a',
  'account_key': 'account-a',
  'mt5_login': 100001,
  'broker_server': 'Broker-Demo',
  'symbol': 'XAUUSD',
  'deal_ticket': id.hashCode.abs(),
  'trade_type': tradeType,
  'record_type': 'deal',
  'status': 'closed',
  'raw_mt5_type': rawType,
  'lot_size': 100,
  'executed_volume': 100,
  'open_time': DateTime.utc(2026, 7, 1, 9),
  'close_time': DateTime.utc(2026, 7, 1, 9),
  'profit': 100,
});

Mt5TradeRecord _lifecycleDeal(
  String id, {
  required int positionId,
  required int dealEntry,
  required String direction,
  required DateTime time,
  required double profit,
  double commission = 0,
}) => Mt5TradeRecord.fromJson({
  'id': id,
  'user_id': 'user-a',
  'account_key': 'account-a',
  'mt5_login': 100001,
  'broker_server': 'Broker-Demo',
  'symbol': 'XAUUSD',
  'position_id': positionId,
  'deal_ticket': id.hashCode.abs(),
  'trade_type': direction,
  'record_type': 'deal',
  'status': 'closed',
  'raw_mt5_type': direction == 'buy' ? 0 : 1,
  'raw_deal_entry': dealEntry,
  'lot_size': .4,
  'executed_volume': .4,
  'has_entry_deal': dealEntry == 0,
  'has_exit_deal': dealEntry == 1,
  'entry_price': 2300,
  'current_price': dealEntry == 1 ? 2310 : 2300,
  'open_time': time,
  'close_time': time,
  'profit': profit,
  'commission': commission,
});
