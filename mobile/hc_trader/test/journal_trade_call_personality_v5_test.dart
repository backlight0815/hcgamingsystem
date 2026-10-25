import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/models/capital_transaction.dart';
import 'package:hc_trader/models/trade_call_statistics.dart';
import 'package:hc_trader/models/trading_data_source.dart';
import 'package:hc_trader/models/trading_date_filter.dart';
import 'package:hc_trader/models/trading_journal_entry.dart';
import 'package:hc_trader/models/trading_plan_recommendation.dart';
import 'package:hc_trader/models/trading_report.dart';
import 'package:hc_trader/models/trading_signal_item.dart';
import 'package:hc_trader/services/personality_guidance_service.dart';
import 'package:hc_trader/services/trade_call_statistics_service.dart';
import 'package:hc_trader/services/trading_date_filter_session.dart';
import 'package:hc_trader/services/trading_report_service.dart';
import 'package:hc_trader/shared/trading_date_filter_bar.dart';

void main() {
  group('Unified trading date filter', () {
    test('supports all modes and inclusive local-day boundaries', () {
      const all = TradingDateFilter.allTime();
      final date = TradingDateFilter.specificDate(DateTime(2026, 7, 1));
      final range = TradingDateFilter.dateRange(
        DateTime(2026, 7, 1),
        DateTime(2026, 7, 3),
      );
      final month = TradingDateFilter.month(2026, 7);
      final year = TradingDateFilter.year(2026);

      expect(all.matches(DateTime(1990)), isTrue);
      expect(date.matches(DateTime(2026, 7, 1)), isTrue);
      expect(date.matches(DateTime(2026, 7, 1, 23, 59, 59)), isTrue);
      expect(date.matches(DateTime(2026, 7, 2)), isFalse);
      expect(range.matches(DateTime(2026, 7, 3, 23, 59)), isTrue);
      expect(range.matches(DateTime(2026, 7, 4)), isFalse);
      expect(month.matches(DateTime(2026, 7, 31, 23, 59)), isTrue);
      expect(month.matches(DateTime(2026, 8)), isFalse);
      expect(year.matches(DateTime(2026, 12, 31, 23, 59)), isTrue);
      expect(year.matches(DateTime(2027)), isFalse);
    });

    test('converts UTC timestamps using the selected user offset', () {
      final date = TradingDateFilter.specificDate(
        DateTime(2026, 7, 1),
        timezone: 'Asia/Singapore',
        timezoneOffsetMinutes: 480,
      );
      expect(date.matches(DateTime.utc(2026, 6, 30, 16)), isTrue);
      expect(date.matches(DateTime.utc(2026, 6, 30, 15, 59, 59)), isFalse);
      expect(date.matches(DateTime.utc(2026, 7, 1, 15, 59, 59)), isTrue);
      expect(date.matches(DateTime.utc(2026, 7, 1, 16)), isFalse);
    });

    test('rejects invalid ranges and preserves temporary session state', () {
      expect(
        () => TradingDateFilter.dateRange(
          DateTime(2026, 7, 2),
          DateTime(2026, 7, 1),
        ),
        throwsArgumentError,
      );
      TradingDateFilterSession.clear('filter-user');
      final selected = TradingDateFilter.month(2026, 7);
      TradingDateFilterSession.write('filter-user', selected);
      expect(
        TradingDateFilterSession.read('filter-user').cacheKey,
        selected.cacheKey,
      );
      TradingDateFilterSession.clear('filter-user');
    });

    testWidgets('filter control remains usable on a small phone', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(320, 640));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await tester.pumpWidget(
        _localizedApp(
          TradingDateFilterBar(
            filter: const TradingDateFilter.allTime(),
            onChanged: (_) {},
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byIcon(Icons.filter_alt_outlined));
      await tester.pumpAndSettle();
      expect(find.text('Specific date'), findsOneWidget);
      expect(find.text('Date range'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });

  group('Date-filtered journal reports', () {
    final entries = <TradingJournalEntry>[
      _trade('manual-july-1', DateTime(2026, 7, 1, 23), 40),
      _trade(
        'excel-july-2',
        DateTime(2026, 7, 2),
        -10,
        source: TradingRecordSource.excelImport,
      ),
      _trade(
        'mt5-main-july-2',
        DateTime(2026, 7, 2),
        70,
        source: TradingRecordSource.mt5Sync,
        accountId: 'main',
      ),
      _trade(
        'mt5-other-july-2',
        DateTime(2026, 7, 2),
        900,
        source: TradingRecordSource.mt5Sync,
        accountId: 'other',
      ),
      _trade('cancelled', DateTime(2026, 7, 2), 500, status: 'cancelled'),
      _trade('august', DateTime(2026, 8, 1), 1000),
    ];

    test(
      'same filter drives list, statistics, source and main account scope',
      () {
        final filter = TradingDateFilter.dateRange(
          DateTime(2026, 7, 1),
          DateTime(2026, 7, 2),
        );
        final manual = TradingReportService().build(
          journals: entries,
          capitals: const [],
          filter: TradingReportFilter(
            dateFilter: filter,
            source: TradingReportSource.manual,
          ),
        );
        final mt5 = TradingReportService().build(
          journals: entries,
          capitals: const [],
          filter: TradingReportFilter(
            dateFilter: filter,
            source: TradingReportSource.mt5,
            mainMt5AccountId: 'main',
          ),
        );

        expect(manual.trades.map((item) => item.id), [
          'manual-july-1',
          'excel-july-2',
        ]);
        expect(manual.summary.totalTrades, 2);
        expect(manual.summary.netProfitLoss, 30);
        expect(manual.summary.winningTrades, 1);
        expect(manual.summary.losingTrades, 1);
        expect(mt5.trades.map((item) => item.id), ['mt5-main-july-2']);
        expect(mt5.summary.netProfitLoss, 70);
      },
    );

    test('capital is reported but never included in trading P/L', () {
      final report = TradingReportService().build(
        journals: entries,
        capitals: [
          CapitalTransaction(
            id: 'deposit',
            userId: 'user-a',
            depositDate: DateTime(2026, 7, 1),
            amount: 10000,
            type: 1,
          ),
          CapitalTransaction(
            id: 'withdrawal',
            userId: 'user-a',
            depositDate: DateTime(2026, 7, 2),
            amount: -100,
            type: 2,
          ),
        ],
        filter: TradingReportFilter(
          dateFilter: TradingDateFilter.month(2026, 7),
          source: TradingReportSource.manual,
        ),
      );
      expect(report.summary.netProfitLoss, 30);
      expect(report.capital.totalDeposits, 10000);
      expect(report.capital.totalWithdrawals, 100);
      expect(
        report.trades.map((item) => item.id),
        isNot(contains('cancelled')),
      );
    });

    test('empty filtered period produces a consistent empty report', () {
      final report = TradingReportService().build(
        journals: entries,
        capitals: const [],
        filter: TradingReportFilter(
          dateFilter: TradingDateFilter.year(2024),
          source: TradingReportSource.manual,
        ),
      );
      expect(report.trades, isEmpty);
      expect(report.summary.totalTrades, 0);
      expect(report.summary.netProfitLoss, 0);
    });
  });

  group('Trade-call statistics policy', () {
    const admin = AppUser(uid: 'admin', email: 'a@test', roleId: 1);
    const provider = AppUser(uid: 'provider-a', email: 'p@test', roleId: 201);
    final calls = <TradingSignalItem>[
      _call('win', 2, provider: 'provider-a', pips: 100),
      _call('loss', 13, provider: 'provider-a', pips: -40),
      _call('be', 15, provider: 'provider-a', pips: 0),
      _call('active', 1, provider: 'provider-a', lifecycle: 'active'),
      _call('draft', 0, provider: 'provider-a', lifecycle: 'draft'),
      _call('cancelled', 12, provider: 'provider-a'),
      _call(
        'expired-unactivated',
        0,
        provider: 'provider-a',
        lifecycle: 'expired',
        activated: false,
      ),
      _call('test', 2, provider: 'provider-a', pips: 200, isTest: true),
      _call('other-provider', 2, provider: 'provider-b', pips: 50),
    ];

    test('uses evaluated calls only and counts a multi-target call once', () {
      final value = const TradeCallStatisticsService().calculate(
        calls: calls.where((call) => call.providerId == 'provider-a'),
        viewer: admin,
      );
      expect(value.totalCalls, 8);
      expect(value.evaluatedCalls, 3);
      expect(value.winningCalls, 1);
      expect(value.losingCalls, 1);
      expect(value.breakEvenCalls, 1);
      expect(value.winRate, closeTo(33.333, .01));
      expect(value.lossRate, closeTo(33.333, .01));
      expect(value.breakEvenRate, closeTo(33.333, .01));
      expect(value.takeProfitCalls, 1);
      expect(value.totalPips, 60);
      expect(value.totalPositivePips, 100);
      expect(value.totalNegativePips, -40);
      expect(value.averageHoldingDuration, const Duration(hours: 2));
    });

    test('calculates symbol-aware pips', () {
      expect(
        TradeCallStatisticsService.calculatePips(
          entry: 150,
          exit: 150.1,
          symbol: 'USDJPY',
          direction: 'Buy',
        ),
        closeTo(10, .0001),
      );
      expect(
        TradeCallStatisticsService.calculatePips(
          entry: 2300,
          exit: 2301,
          symbol: 'XAUUSD',
          direction: 'Buy',
        ),
        closeTo(10, .0001),
      );
      expect(
        TradeCallStatisticsService.calculatePips(
          entry: 1.1,
          exit: 1.101,
          symbol: 'EURUSD',
          direction: 'Buy',
        ),
        closeTo(10, .0001),
      );
    });

    test('locks providers to themselves and allows admin comparison', () {
      final own = const TradeCallStatisticsService().calculate(
        calls: calls,
        viewer: provider,
      );
      expect(own.totalCalls, 8);
      expect(own.byProvider.map((value) => value.key), ['provider-a']);
      expect(
        () => const TradeCallStatisticsService().calculate(
          calls: calls,
          viewer: provider,
          filter: const TradeCallStatisticsFilter(providerId: 'provider-b'),
        ),
        throwsA(isA<TradeCallAccessException>()),
      );
      final comparison = const TradeCallStatisticsService().calculate(
        calls: calls,
        viewer: admin,
      );
      expect(comparison.byProvider.length, 2);
    });

    test('date and instrument filters are applied before aggregation', () {
      final value = const TradeCallStatisticsService().calculate(
        calls: calls,
        viewer: admin,
        filter: TradeCallStatisticsFilter(
          dateFilter: TradingDateFilter.specificDate(DateTime(2026, 7, 10)),
          instrument: 'EURUSD',
        ),
      );
      expect(value.totalCalls, calls.length);
      final empty = const TradeCallStatisticsService().calculate(
        calls: calls,
        viewer: admin,
        filter: TradeCallStatisticsFilter(
          dateFilter: TradingDateFilter.specificDate(DateTime(2025, 1, 1)),
        ),
      );
      expect(empty.totalCalls, 0);
      expect(empty.evaluatedCalls, 0);
    });
  });

  group('Personality guidance', () {
    test('incomplete and missing results do not generate guidance', () {
      const service = PersonalityGuidanceService();
      expect(
        service.generate(
          response: const TradingPlanQuestionnaireResponse(),
          resultVersion: 'v1',
        ),
        isNull,
      );
    });

    test('maps supported answers without mutating plan or strategy data', () {
      const originalAnswers = <String, String>{
        'personality': 'impulsive',
        'loss_reaction': 'want_win_back',
        'biggest_weakness': 'fomo',
        'psychology_issues': 'fomo|overtrading|revenge_trading',
        'risk_per_trade': '1',
        'max_trades_per_day': '2',
      };
      const response = TradingPlanQuestionnaireResponse(
        answers: originalAnswers,
      );
      final generatedAt = DateTime.utc(2026, 7, 13);
      final result = PersonalityGuidanceService(
        now: () => generatedAt,
      ).generate(response: response, resultVersion: 'questionnaire-v4')!;

      expect(result.riskAreas, containsAll(['FOMO', 'Revenge trading']));
      expect(result.items.map((item) => item.id), contains('fomo-rule'));
      expect(result.items.map((item) => item.id), contains('revenge-cooldown'));
      expect(result.resultVersion, 'questionnaire-v4');
      expect(result.generatedAt, generatedAt);
      final draft = result.draftFor({'fomo-rule'});
      expect((draft['selected_guidance'] as List).length, 1);
      expect(response.answers, originalAnswers);
      expect(draft, isNot(contains('trading_plan')));
      expect(draft, isNot(contains('trading_strategy')));
    });
  });

  test('Data Platform module references are absent from Flutter source', () {
    const obsolete = [
      'Data Platform',
      'DataPlatform',
      'data_platform',
      'dataPlatform',
    ];
    final dartFiles = Directory('lib')
        .listSync(recursive: true)
        .whereType<File>()
        .where((file) => file.path.endsWith('.dart'));
    for (final file in dartFiles) {
      final source = file.readAsStringSync();
      for (final token in obsolete) {
        expect(source, isNot(contains(token)), reason: '${file.path}: $token');
      }
    }
  });

  test('new date, statistics, and guidance labels are localized', () {
    const zh = AppLocalizations(Locale('zh'));
    for (final key in [
      'Specific date',
      'Trade-Call Statistics',
      'Average realized R:R',
      'Personality Trading Guidance',
      'Educational guidance only',
    ]) {
      expect(zh.text(key), isNot(key), reason: key);
    }
  });
}

Widget _localizedApp(Widget home) => MaterialApp(
  locale: const Locale('en'),
  supportedLocales: AppLocalizations.supportedLocales,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  home: Scaffold(body: home),
);

TradingJournalEntry _trade(
  String id,
  DateTime close,
  double profit, {
  TradingRecordSource source = TradingRecordSource.manual,
  String? accountId,
  String status = 'closed',
}) => TradingJournalEntry(
  id: id,
  userId: 'user-a',
  openDate: close.subtract(const Duration(hours: 1)),
  closeDate: close,
  pair: 'EURUSD',
  direction: 1,
  entryPrice: 1.1,
  exitPrice: 1.101,
  lotSize: .1,
  pips: profit.sign * 10,
  profitLoss: profit,
  result: profit > 0
      ? 1
      : profit < 0
      ? 2
      : 3,
  source: source,
  tradingAccountId: accountId,
  status: status,
);

TradingSignalItem _call(
  String id,
  int status, {
  required String provider,
  int? pips,
  String lifecycle = '',
  bool activated = true,
  bool isTest = false,
}) => TradingSignalItem(
  id: id,
  tradingPair: 'EURUSD',
  immediateAction: 'Buy now',
  entryPrice: '1.1000',
  stopLoss: '1.0950',
  targets: const ['1.1100', '1.1200'],
  status: status,
  isDone: status == 2 || status == 13 || status == 15,
  isBreakEven: status == 15,
  pipsResult: pips,
  providerId: provider,
  analyst: provider,
  signalSource: 'desk',
  lifecycleStatus: lifecycle,
  orderType: 'market',
  createdAt: DateTime(2026, 7, 10, 7),
  triggerTime: activated ? DateTime(2026, 7, 10, 8) : null,
  activatedAt: activated ? DateTime(2026, 7, 10, 8) : null,
  closedAt: status == 2 || status == 13 || status == 15
      ? DateTime(2026, 7, 10, 10)
      : null,
  expiryTime: id == 'expired-unactivated' ? DateTime(2026, 7, 9) : null,
  isTest: isTest,
);
