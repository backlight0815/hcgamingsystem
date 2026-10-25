import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:archive/archive.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/features/journal/mt5_excel_import_screen.dart';
import 'package:hc_trader/models/capital_transaction.dart';
import 'package:hc_trader/models/mt5_import_models.dart';
import 'package:hc_trader/models/mt5_sync_models.dart';
import 'package:hc_trader/models/trading_report.dart';
import 'package:hc_trader/services/mt5_import/mt5_import_coordinator.dart';
import 'package:hc_trader/services/mt5_import/mt5_import_parser.dart';
import 'package:hc_trader/services/mt5_import/mt5_value_parser.dart';
import 'package:hc_trader/services/mt5_trade_eligibility_service.dart';
import 'package:hc_trader/services/trading_analytics.dart';
import 'package:hc_trader/services/trading_data_source_service.dart';
import 'package:hc_trader/services/trading_report_service.dart';

void main() {
  group('MT5 value parsing', () {
    const values = Mt5ValueParser();

    test('uses fixed precision for localized and negative values', () {
      expect(Mt5Decimal.parse('1,234.56').toString(), '1234.56');
      expect(Mt5Decimal.parse('1.234,56').toString(), '1234.56');
      expect(Mt5Decimal.parse('(24.64)').toString(), '-24.64');
      expect(Mt5Decimal.parse('−12.3200').toString(), '-12.32');
      expect(
        Mt5Decimal.parse('0.1') + Mt5Decimal.parse('0.2'),
        Mt5Decimal.parse('0.3'),
      );
    });

    test('parses MT5 dates, Excel dates, volume pairs, and states', () {
      expect(
        values.dateTime('2026.07.14 19:05:29', date1904: false),
        DateTime(2026, 7, 14, 19, 5, 29),
      );
      expect(values.dateTime('2', date1904: false), DateTime(1900, 1, 1));
      final volume = values.volumePair('0.11 / 0.10');
      expect(volume.requested.toString(), '0.11');
      expect(volume.executed.toString(), '0.1');
      expect(values.orderState('Canceled'), 'cancelled');
      expect(values.orderState('Partially filled'), 'partially_filled');
      expect(values.dealDirection('In/Out'), 'in_out');
      expect(values.dealDirection('Out By'), 'out_by');
    });

    test('keeps blank SL and TP nullable and preserves market prices', () {
      expect(values.nullableDecimal(''), isNull);
      expect(values.nullableDecimal('   '), isNull);
      expect(values.normalizeLabel('market'), 'market');
    });

    test('uses imported closing balance when live MT5 data is stale', () {
      final account = Mt5Account(
        id: 'Broker_42',
        userId: 'user-a',
        accountKey: 'Broker_42',
        login: 42,
        brokerServer: 'Broker',
        balance: 1000,
        latestImportedClosingBalance: 975.36,
      );
      expect(mt5AccountCapitalBalance(account), 975.36);
    });

    test('keeps large MT5 tickets as strings and includes fees in net P/L', () {
      final record = Mt5TradeRecord.fromJson({
        'id': 'large-ticket',
        'user_id': 'user-a',
        'account_key': 'Broker_42',
        'mt5_login': 42,
        'broker_server': 'Broker',
        'symbol': 'EURUSD',
        'trade_type': 'buy',
        'record_type': 'position',
        'status': 'closed',
        'unique_mt5_id': 'large-ticket',
        'source_position_ticket': '999999999999999999999999',
        'lot_size': 1,
        'profit': 10,
        'commission': -1,
        'fee': -2,
        'swap': -0.5,
      });
      expect(record.positionId, isNull);
      expect(record.canonicalPositionTicket, '999999999999999999999999');
      expect(record.hasValidPosition, isTrue);
      expect(record.netProfit, 6.5);
    });
  });

  group('sample.xlsx regression fixture', () {
    late Mt5ImportPreview preview;

    setUpAll(() {
      final fixture = File('test/fixtures/mt5/sample.xlsx');
      expect(
        fixture.existsSync(),
        isTrue,
        reason: 'Permanent MT5 fixture missing',
      );
      preview = Mt5ImportParser().parse(
        bytes: Uint8List.fromList(fixture.readAsBytesSync()),
        filename: 'sample.xlsx',
      );
    });

    test('detects report metadata and dynamic sections', () {
      expect(
        preview.fileHash,
        'cb50449ee2703a7dfd8d0fa23052ec8bec190fb6aa90f188f681bf1bf982f5cf',
      );
      expect(preview.parserProfile, 'mt5_trade_history_sections_v1');
      expect(preview.sheetNames, contains('Sheet1'));
      expect(
        preview.detectedSections,
        containsAll(<Mt5ImportSection>{
          Mt5ImportSection.positions,
          Mt5ImportSection.orders,
          Mt5ImportSection.deals,
          Mt5ImportSection.results,
        }),
      );
      expect(preview.metadata.reportName, 'Trade History Report');
      expect(preview.metadata.accountNumber, '700138229');
      expect(preview.metadata.currency, 'USD');
      expect(preview.metadata.brokerServer, 'PUPrime-Demo');
      expect(preview.metadata.environment, 'demo');
      expect(preview.metadata.accountMode, 'Hedge');
      expect(preview.metadata.reportGeneratedAt, DateTime(2026, 7, 14, 16, 18));
      expect(preview.formatConfidence, greaterThanOrEqualTo(0.95));
    });

    test('maps positions, orders, and deals without collapsing tickets', () {
      expect(preview.positions, hasLength(2));
      expect(preview.orders, hasLength(4));
      expect(preview.deals, hasLength(5));
      expect(preview.filledOrders, 4);
      expect(preview.pendingOrders, 0);
      expect(preview.inactiveOrders, 0);
      expect(preview.positions.map((item) => item.ticket).toSet(), {
        '289358202',
        '289358205',
      });
      expect(
        preview.deals
            .where((item) => item.isTradeEntry)
            .map((item) => item.ticket)
            .toSet(),
        {'219519817', '219519818'},
      );
      expect(
        preview.deals
            .where((item) => item.isTradeExit)
            .map((item) => item.ticket)
            .toSet(),
        {'219520165', '219520166'},
      );
      expect(
        preview.positions.every(
          (position) =>
              position.relatedDealTickets.length == 2 &&
              position.relatedOrderTickets.length == 2,
        ),
        isTrue,
      );
      expect(
        preview.positions.every((item) => item.side == 'buy'),
        isTrue,
        reason: 'Closing sell orders must not create short positions',
      );
      expect(preview.positions.every((item) => item.stopLoss == null), isTrue);
      expect(
        preview.positions.every((item) => item.takeProfit == null),
        isTrue,
      );
      expect(
        preview.positions.every((item) => item.symbol == 'XAUUSD.s'),
        isTrue,
      );
    });

    test('classifies deposit separately and reconciles financial totals', () {
      expect(preview.balanceTransactions, hasLength(1));
      final deposit = preview.balanceTransactions.single;
      expect(deposit.dealTicket, '219391435');
      expect(deposit.classification, Mt5DealClassification.deposit);
      expect(deposit.amount.toString(), '1000');
      expect(preview.deposits, 1);
      expect(preview.withdrawals, 0);

      final summary = preview.financialSummary;
      expect(summary.grossProfit.toString(), '0');
      expect(summary.grossLoss.toString(), '-24.64');
      expect(summary.commission.toString(), '0');
      expect(summary.fee.toString(), '0');
      expect(summary.swap.toString(), '0');
      expect(summary.netTradingPnl.toString(), '-24.64');
      expect(summary.netCapitalFlow.toString(), '1000');
      expect(summary.openingBalance.toString(), '0');
      expect(summary.sourceClosingBalance.toString(), '975.36');
      expect(summary.calculatedClosingBalance.toString(), '975.36');
      expect(summary.reconciliationDifference.toString(), '0');
      expect(summary.reconciled, isTrue);
      expect(preview.completedPositions, 2);
      expect(preview.hasBlockingErrors, isFalse);
      expect(
        preview.resultComparisons
            .firstWhere((item) => item.key == 'total_net_profit')
            .matches,
        isTrue,
      );
    });

    test(
      'feeds exactly two trades and separate capital into shared reports',
      () {
        final records = <Mt5TradeRecord>[
          for (final position in preview.positions)
            Mt5TradeRecord.fromJson({
              'id': 'position-${position.ticket}',
              'user_id': 'user-a',
              'account_key': 'PUPrime-Demo_700138229',
              'mt5_login': 700138229,
              'broker_server': 'PUPrime-Demo',
              'source_position_ticket': position.ticket,
              'unique_mt5_id': 'position-${position.ticket}',
              'symbol': position.symbol,
              'trade_type': position.side,
              'record_type': 'position',
              'status': 'closed',
              'lot_size': position.volume.toDouble(),
              'entry_price': position.openPrice.toDouble(),
              'close_price': position.closePrice!.toDouble(),
              'open_time': position.openTime.toIso8601String(),
              'close_time': position.closeTime!.toIso8601String(),
              'profit': position.grossPriceProfit.toDouble(),
              'commission': position.commission.toDouble(),
              'fee': position.fee.toDouble(),
              'swap': position.swap.toDouble(),
              'executed_volume': position.volume.toDouble(),
              'has_entry_deal': true,
              'has_exit_deal': true,
              'was_triggered': true,
            }),
          for (final order in preview.orders)
            Mt5TradeRecord.fromJson({
              'id': 'order-${order.ticket}',
              'user_id': 'user-a',
              'account_key': 'PUPrime-Demo_700138229',
              'mt5_login': 700138229,
              'broker_server': 'PUPrime-Demo',
              'source_order_ticket': order.ticket,
              'unique_mt5_id': 'order-${order.ticket}',
              'symbol': order.symbol,
              'trade_type': order.orderType,
              'record_type': 'order',
              'status': order.state,
              'executed_volume': order.executedVolume.toDouble(),
              'open_time': order.createdAt.toIso8601String(),
            }),
        ];
        final eligible = const Mt5TradeEligibilityService().build(
          records,
          userId: 'user-a',
          accountKey: 'PUPrime-Demo_700138229',
        );
        final journals = eligible.executedTrades
            .map(journalFromMt5Record)
            .toList(growable: false);
        final capitals = [
          CapitalTransaction(
            id: 'deposit-219391435',
            userId: 'user-a',
            depositDate: preview.balanceTransactions.single.time,
            amount: 1000,
            type: 1,
            tradingAccountId: 'PUPrime-Demo_700138229',
          ),
        ];

        expect(journals, hasLength(2));
        expect(journals.every((journal) => journal.direction == 1), isTrue);
        final analytics = TradingAnalytics().summarize(
          journals: journals,
          capitals: capitals,
        );
        expect(analytics.totalTrades, 2);
        expect(analytics.netProfitLoss, -24.64);
        expect(analytics.totalDeposits, 1000);
        expect(analytics.currentBalance, 975.36);
        final report = TradingReportService().build(
          journals: journals,
          capitals: capitals,
          filter: const TradingReportFilter(
            source: TradingReportSource.mt5,
            mainMt5AccountId: 'PUPrime-Demo_700138229',
          ),
        );
        expect(report.summary.totalTrades, 2);
        expect(report.summary.netProfitLoss, -24.64);
        expect(report.summary.currentBalance, 975.36);
      },
    );
  });

  group('broker format profiles and workbook safety', () {
    test('supports a flat deal history and cached formula results', () {
      final workbook = _flatDealWorkbook();
      final preview = Mt5ImportParser().parse(
        bytes: workbook,
        filename: 'broker-flat.xlsx',
      );

      expect(preview.parserProfile, 'mt5_flat_deal_history_v1');
      expect(preview.metadata.accountNumber, '42');
      expect(preview.metadata.brokerServer, 'Broker-Demo');
      expect(preview.deals, hasLength(3));
      expect(preview.positions, hasLength(1));
      expect(preview.positions.single.symbol, 'EURUSD.pro');
      expect(preview.positions.single.side, 'buy');
      expect(
        preview.balanceTransactions.single.classification,
        Mt5DealClassification.deposit,
      );
      expect(preview.financialSummary.grossLoss.toString(), '-8');
      expect(preview.financialSummary.commission.toString(), '-0.5');
      expect(preview.financialSummary.fee.toString(), '-1.25');
      expect(preview.financialSummary.swap.toString(), '-0.25');
      expect(preview.financialSummary.netTradingPnl.toString(), '-10');
      expect(preview.financialSummary.sourceClosingBalance.toString(), '990');
      expect(preview.financialSummary.reconciled, isTrue);
      expect(preview.hasBlockingErrors, isFalse);
    });

    test('rejects non-OpenXML and excessively ambiguous input safely', () {
      expect(
        () => Mt5ImportParser().parse(
          bytes: Uint8List.fromList(utf8.encode('not a workbook')),
          filename: 'unsafe.xlsx',
        ),
        throwsA(isA<Exception>()),
      );
    });
  });

  testWidgets('import preview is localized and responsive on a narrow screen', (
    tester,
  ) async {
    final bytes = Uint8List.fromList(
      File('test/fixtures/mt5/sample.xlsx').readAsBytesSync(),
    );
    final preview = Mt5ImportParser().parse(
      bytes: bytes,
      filename: 'sample.xlsx',
    );
    final account = Mt5Account(
      id: 'PUPrime-Demo_700138229',
      userId: 'user-a',
      accountKey: 'PUPrime-Demo_700138229',
      login: 700138229,
      brokerServer: 'PUPrime-Demo',
      currency: 'USD',
      serverTimezone: 'Broker server UTC+03:00',
      serverTimezoneOffsetMinutes: 180,
    );
    tester.view.physicalSize = const Size(320, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('zh'),
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: const [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: Mt5ExcelImportScreen(
          bytes: bytes,
          filename: 'sample.xlsx',
          accounts: [account],
          coordinator: _FakeCoordinator(preview),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('MT5 桌面版 Excel 导入'), findsOneWidget);
    expect(
      const AppLocalizations(Locale('zh')).text('Financial preview'),
      '财务预览',
    );
    expect(tester.takeException(), isNull);
  });
}

class _FakeCoordinator extends Mt5ImportCoordinator {
  _FakeCoordinator(this.preview);

  final Mt5ImportPreview preview;

  @override
  Future<Mt5ImportPreview> parseWorkbook({
    required Uint8List bytes,
    required String filename,
  }) async => preview;

  @override
  Future<Mt5ImportPreview> loadDuplicatePreview({
    required Mt5ImportPreview preview,
    required Mt5Account account,
  }) async => preview.copyWith(
    duplicateSummary: const Mt5DuplicateSummary(newRecords: 12),
  );
}

Uint8List _flatDealWorkbook() {
  final rows = <List<String>>[
    ['Trade History Report'],
    ['Account', '42 (USD, Broker-Demo, demo, Hedge)'],
    ['Company', 'Broker Ltd'],
    ['Date', '2026.07.14 20:00'],
    [
      'Time',
      'Deal',
      'Symbol',
      'Type',
      'Direction',
      'Volume',
      'Price',
      'Order',
      'Commission',
      'Fee',
      'Swap',
      'Profit',
      'Balance',
      'Comment',
    ],
    [
      '2026.07.14 10:00:00',
      '9001',
      '',
      'balance',
      '',
      '',
      '',
      '',
      '0',
      '0',
      '0',
      '1000',
      '1000',
      'Deposit',
    ],
    [
      '2026.07.14 11:00:00',
      '9002',
      'EURUSD.pro',
      'buy',
      'in',
      '0.10',
      '1.10000',
      '8001',
      '0',
      '0',
      '0',
      '0',
      '',
      '',
    ],
    [
      '2026.07.14 12:00:00',
      '9003',
      'EURUSD.pro',
      'sell',
      'out',
      '0.10',
      '1.09900',
      '8002',
      '-0.50',
      '-1.25',
      '-0.25',
      '-8',
      '990',
      '',
    ],
  ];
  final sheetRows = <String>[];
  for (var rowIndex = 0; rowIndex < rows.length; rowIndex++) {
    final cells = <String>[];
    for (var column = 0; column < rows[rowIndex].length; column++) {
      final reference = '${_columnName(column)}${rowIndex + 1}';
      final value = rows[rowIndex][column];
      final numeric = value.isNotEmpty && num.tryParse(value) != null;
      if (rowIndex == 7 && column == 11) {
        cells.add('<c r="$reference"><f>2-10</f><v>-8</v></c>');
      } else if (numeric) {
        cells.add('<c r="$reference"><v>$value</v></c>');
      } else {
        cells.add(
          '<c r="$reference" t="inlineStr"><is><t>${_xml(value)}</t></is></c>',
        );
      }
    }
    sheetRows.add('<row r="${rowIndex + 1}">${cells.join()}</row>');
  }
  final archive = Archive()
    ..addFile(
      ArchiveFile.string('xl/workbook.xml', '''
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
 xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
 <workbookPr/><sheets><sheet name="History" sheetId="1" r:id="rId1"/></sheets>
</workbook>'''),
    )
    ..addFile(
      ArchiveFile.string('xl/_rels/workbook.xml.rels', '''
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
 <Relationship Id="rId1" Type="worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>'''),
    )
    ..addFile(
      ArchiveFile.string('xl/worksheets/sheet1.xml', '''
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
 <sheetData>${sheetRows.join()}</sheetData>
</worksheet>'''),
    );
  return Uint8List.fromList(ZipEncoder().encode(archive)!);
}

String _columnName(int zeroBased) {
  var value = zeroBased + 1;
  var result = '';
  while (value > 0) {
    value--;
    result = String.fromCharCode(65 + value % 26) + result;
    value ~/= 26;
  }
  return result;
}

String _xml(String value) => value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;');
