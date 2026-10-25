import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/widgets.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/models/capital_transaction.dart';
import 'package:hc_trader/models/trading_data_source.dart';
import 'package:hc_trader/models/trading_journal_entry.dart';
import 'package:hc_trader/models/trading_report.dart';
import 'package:hc_trader/services/trading_report_pdf_service.dart';
import 'package:hc_trader/services/trading_report_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('Account-scoped trading reports', () {
    final trades = <TradingJournalEntry>[
      _trade('manual', profit: 10),
      _trade('excel', profit: 20, source: TradingRecordSource.excelImport),
      _trade(
        'account-a-win',
        profit: 100,
        source: TradingRecordSource.mt5Sync,
        accountId: 'account-a',
      ),
      _trade(
        'account-a-loss',
        day: 2,
        profit: -25,
        source: TradingRecordSource.mt5Sync,
        accountId: 'account-a',
      ),
      _trade(
        'account-b',
        day: 3,
        profit: 900,
        source: TradingRecordSource.mt5Sync,
        accountId: 'account-b',
      ),
      _trade(
        'other-owner',
        day: 4,
        profit: 5000,
        userId: 'user-b',
        source: TradingRecordSource.mt5Sync,
        accountId: 'account-a',
      ),
    ];

    test('Account A and Account B remain isolated before calculations', () {
      final service = TradingReportService();
      final accountA = service.build(
        journals: trades,
        capitals: const [],
        filter: const TradingReportFilter(
          ownerUid: 'user-a',
          source: TradingReportSource.mt5,
          mainMt5AccountId: 'account-a',
        ),
      );
      final accountB = service.build(
        journals: trades,
        capitals: const [],
        filter: const TradingReportFilter(
          ownerUid: 'user-a',
          source: TradingReportSource.mt5,
          mainMt5AccountId: 'account-b',
        ),
      );

      expect(accountA.trades.map((item) => item.id), [
        'account-a-win',
        'account-a-loss',
      ]);
      expect(accountA.summary.netProfitLoss, 75);
      expect(accountA.summary.winningTrades, 1);
      expect(accountA.summary.losingTrades, 1);
      expect(accountB.trades.map((item) => item.id), ['account-b']);
      expect(accountB.summary.netProfitLoss, 900);
      expect(accountB.trades, isNot(contains(accountA.trades.first)));
    });

    test('Manual, MT5, and All source rules are explicit', () {
      final service = TradingReportService();
      final manual = service.build(
        journals: trades,
        capitals: const [],
        filter: const TradingReportFilter(
          ownerUid: 'user-a',
          source: TradingReportSource.manual,
        ),
      );
      final mt5 = service.build(
        journals: trades,
        capitals: const [],
        filter: const TradingReportFilter(
          ownerUid: 'user-a',
          source: TradingReportSource.mt5,
          mainMt5AccountId: 'account-a',
        ),
      );
      final all = service.build(
        journals: trades,
        capitals: const [],
        filter: const TradingReportFilter(
          ownerUid: 'user-a',
          source: TradingReportSource.all,
          mainMt5AccountId: 'account-a',
        ),
      );

      expect(manual.trades.map((item) => item.id), ['manual', 'excel']);
      expect(mt5.trades.map((item) => item.id), [
        'account-a-win',
        'account-a-loss',
      ]);
      expect(all.trades.map((item) => item.id), [
        'manual',
        'excel',
        'account-a-win',
        'account-a-loss',
      ]);
      expect(all.trades.map((item) => item.id), isNot(contains('account-b')));
    });

    test('month, year, and owner filters run before score aggregation', () {
      final report = TradingReportService().build(
        journals: [
          ...trades,
          _trade(
            'outside-period',
            day: 1,
            month: 8,
            profit: 999,
            source: TradingRecordSource.mt5Sync,
            accountId: 'account-a',
          ),
        ],
        capitals: const [],
        filter: const TradingReportFilter(
          ownerUid: 'user-a',
          source: TradingReportSource.mt5,
          mainMt5AccountId: 'account-a',
          month: 7,
          year: 2026,
        ),
      );

      expect(report.summary.netProfitLoss, 75);
      expect(report.filter.mainMt5AccountId, 'account-a');
      expect(report.scoreProfile.components, isNotEmpty);
    });
  });

  test('Laravel score bands and position formula remain mapped', () {
    final report = TradingReportService().build(
      journals: [
        for (var index = 0; index < 10; index++)
          _trade(
            'fixture-$index',
            day: index + 1,
            profit: index < 8 ? 50 : -20,
            lot: .1,
          ),
      ],
      capitals: [
        CapitalTransaction(
          id: 'capital',
          userId: 'user-a',
          depositDate: DateTime(2026, 7),
          amount: 1000,
          type: 1,
        ),
      ],
      filter: const TradingReportFilter(
        ownerUid: 'user-a',
        source: TradingReportSource.manual,
      ),
    );

    final winRate = report.scoreProfile.components.firstWhere(
      (item) => item.metric == 'Win Rate',
    );
    final riskReward = report.scoreProfile.components.firstWhere(
      (item) => item.metric == 'Risk Reward',
    );
    expect(report.summary.winRate, 80);
    expect(report.summary.profitFactor, 10);
    expect(winRate.points, 30);
    expect(winRate.grade, 'A');
    expect(riskReward.points, 30);
    expect(riskReward.grade, 'A+');
    expect(report.positionProfile.mainLot, .1);
    expect(report.positionProfile.mainLotShare, 100);
  });

  test('required report messages are localized in Simplified Chinese', () {
    const chinese = AppLocalizations(Locale('zh'));
    expect(
      chinese.text(
        'Select a main MT5 account before generating an MT5 report.',
      ),
      '\u751f\u6210 MT5 \u62a5\u544a\u524d\uff0c\u8bf7\u5148\u9009\u62e9\u4e3b MT5 \u8d26\u6237\u3002',
    );
    expect(
      chinese.text('Advanced Trading Journal Report'),
      '\u9ad8\u7ea7\u4ea4\u6613\u65e5\u5fd7\u62a5\u544a',
    );
    expect(chinese.text('Small Sample Professional Candidate'), '小样本职业候选交易者');
    expect(
      chinese.text(
        'Winning trades divided by closed winning plus losing trades.',
      ),
      isNot(contains('Winning trades')),
    );
    expect(
      chinese.text(
        'Layering is disclosed separately from revenge and gambling penalties.',
      ),
      '分层加仓与报复性交易及赌博行为扣分分开披露。',
    );
  });

  test(
    'professional English and Chinese PDFs render from fixture data',
    () async {
      final trades = [
        for (var index = 0; index < 20; index++)
          _trade(
            'ticket-${1000 + index}',
            day: (index % 20) + 1,
            profit: index % 5 == 0 ? -25.0 : (55 + index).toDouble(),
            lot: index % 4 == 0 ? .2 : .1,
            source: TradingRecordSource.mt5Sync,
            accountId: 'Broker-Demo_700122288',
            notes:
                'Long fixture note to verify wrapping without clipping. Followed the planned setup and reviewed risk before entry.',
          ),
      ];
      final service = TradingReportService();
      const account = TradingReportAccount(
        id: 'Broker-Demo_700122288',
        name: 'Primary Evaluation',
        login: '700122288',
        broker: 'Broker',
        server: 'Broker-Demo',
        currency: 'USD',
        balance: 11250,
        equity: 11240,
      );
      TradingReport report(String language) => service.build(
        journals: trades,
        capitals: const [],
        traderLabel: 'Fixture Trader',
        account: account,
        currentBalanceOverride: account.balance,
        filter: TradingReportFilter(
          ownerUid: 'user-a',
          source: TradingReportSource.mt5,
          mainMt5AccountId: account.id,
          languageCode: language,
        ),
      );

      final pdf = TradingReportPdfService();
      final english = await pdf.build(report: report('en'));
      final chinese = await pdf.build(report: report('zh'));
      expect(english.length, greaterThan(30000));
      expect(chinese.length, greaterThan(30000));

      final output = Directory('../../output/pdf');
      output.createSync(recursive: true);
      File(
        '${output.path}/trading-journal-report-fixture-en.pdf',
      ).writeAsBytesSync(english, flush: true);
      File(
        '${output.path}/trading-journal-report-fixture-zh.pdf',
      ).writeAsBytesSync(chinese, flush: true);
    },
    timeout: const Timeout(Duration(minutes: 2)),
  );
}

TradingJournalEntry _trade(
  String id, {
  int day = 1,
  int month = 7,
  double profit = 0,
  double lot = .1,
  String userId = 'user-a',
  TradingRecordSource source = TradingRecordSource.manual,
  String? accountId,
  String notes = '',
}) {
  final open = DateTime(2026, month, day, 8);
  return TradingJournalEntry(
    id: id,
    userId: userId,
    source: source,
    tradingAccountId: accountId,
    openDate: open,
    closeDate: open.add(const Duration(hours: 1)),
    pair: day.isEven ? 'EURUSD' : 'XAUUSD',
    direction: day.isEven ? 2 : 1,
    entryPrice: day.isEven ? 1.08 : 2300,
    exitPrice: day.isEven ? 1.079 : 2301,
    lotSize: lot,
    pips: profit > 0 ? 10 : -5,
    profitLoss: profit,
    result: profit > 0
        ? 1
        : profit < 0
        ? 2
        : 3,
    notes: notes,
    followedTradingPlan: true,
    disciplineRating: 8,
  );
}
