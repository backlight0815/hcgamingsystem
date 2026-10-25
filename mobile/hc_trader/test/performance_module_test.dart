import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'helpers/pump_analytics.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/security/permission_service.dart';
import 'package:hc_trader/features/statistics/statistics_screen.dart';
import 'package:hc_trader/features/statistics/statistics_view_model.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/models/capital_transaction.dart';
import 'package:hc_trader/models/trading_data_source.dart';
import 'package:hc_trader/models/trading_journal_entry.dart';
import 'package:hc_trader/models/trading_report.dart';
import 'package:hc_trader/repositories/trading_repository.dart';
import 'package:hc_trader/services/trading_report_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('performance calculation safeguards', () {
    test('deduplicates trades and excludes cash flow and inactive records', () {
      final duplicate = _trade('ticket-1', profit: 120);
      final report = TradingReportService().build(
        journals: [
          duplicate,
          duplicate,
          _trade('loss', day: 2, profit: -40),
          _trade('pending', day: 3, profit: 900, status: 'pending'),
          _trade('deposit-row', day: 4, profit: 5000, type: 'deposit'),
        ],
        capitals: [
          _capital('deposit', 10000, 1),
          _capital('withdrawal', -2500, 2),
        ],
      );

      expect(report.summary.totalTrades, 2);
      expect(report.summary.netProfitLoss, 80);
      expect(report.duplicateTradesExcluded, 1);
      expect(report.capital.totalDeposits, 10000);
      expect(report.capital.totalWithdrawals, 2500);
    });

    test(
      'validates win rate, profit factor, expectancy and recovery factor',
      () {
        final report = TradingReportService().build(
          journals: [
            _trade('win-a', profit: 100),
            _trade('win-b', day: 2, profit: 50),
            _trade('loss', day: 3, profit: -50),
            _trade('break-even', day: 4),
          ],
          capitals: [_capital('deposit', 1000, 1)],
        );

        expect(report.summary.winRate, 66.67);
        expect(report.summary.profitFactor, 3);
        expect(report.summary.expectancy, 25);
        expect(report.summary.maxDrawdownAmount, 50);
        expect(report.summary.recoveryFactor, 2);
      },
    );

    test('scopes manual, Excel, and selected MT5 data without overlap', () {
      final records = [
        _trade('manual', profit: 10),
        _trade(
          'excel',
          day: 2,
          profit: 20,
          source: TradingRecordSource.excelImport,
        ),
        _trade(
          'main',
          day: 3,
          profit: 30,
          source: TradingRecordSource.mt5Sync,
          account: 'main-account',
        ),
        _trade(
          'other',
          day: 4,
          profit: 300,
          source: TradingRecordSource.mt5Sync,
          account: 'other-account',
        ),
      ];
      final service = TradingReportService();
      final manual = service.build(
        journals: records,
        capitals: const [],
        filter: const TradingReportFilter(source: TradingReportSource.manual),
      );
      final selectedMt5 = service.build(
        journals: records,
        capitals: const [],
        filter: const TradingReportFilter(
          source: TradingReportSource.mt5,
          mainMt5AccountId: 'main-account',
        ),
      );
      final allMt5 = service.build(
        journals: records,
        capitals: const [],
        filter: const TradingReportFilter(source: TradingReportSource.mt5),
      );

      expect(manual.summary.netProfitLoss, 30);
      expect(selectedMt5.summary.netProfitLoss, 30);
      expect(allMt5.summary.netProfitLoss, 330);
    });

    test('derives deterministic consistency and concentration insight', () {
      final report = TradingReportService().build(
        journals: [
          _trade('large', profit: 100),
          _trade('small', day: 2, profit: 10),
          _trade('loss', day: 3, profit: -10),
        ],
        capitals: [_capital('deposit', 1000, 1)],
      );
      final model = StatisticsViewModel.fromReport(report);

      expect(model.profitConcentration, closeTo(90.91, .01));
      expect(
        model.insights.map((insight) => insight.title),
        contains('Profit is concentrated'),
      );
      expect(model.consistencyScore, inInclusiveRange(0, 100));
    });

    test('uses the inferred MT5 opening balance for current drawdown', () {
      final report = TradingReportService().build(
        journals: [
          _trade(
            'mt5-loss',
            profit: -100,
            source: TradingRecordSource.mt5Sync,
            account: 'mt5-account',
          ),
        ],
        capitals: const [],
        filter: const TradingReportFilter(
          source: TradingReportSource.mt5,
          mainMt5AccountId: 'mt5-account',
        ),
        currentBalanceOverride: 900,
      );

      final model = StatisticsViewModel.fromReport(report);
      expect(model.currentDrawdownAmount, 100);
      expect(model.currentDrawdownPercent, 10);
    });
  });

  group('performance responsive experience', () {
    testWidgets('small phone renders overview and opens filter sheet', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(360, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final repository = TradingRepository(firebaseEnabled: false);
      const user = AppUser(
        uid: 'performance-phone',
        email: 'phone@example.com',
        permissionOverrides: {AppPermission.statisticsReadOwn: true},
      );
      await repository.upsertJournal(
        _trade('phone-trade', profit: 50, userId: user.uid),
      );

      await tester.pumpWidget(_app(user, repository));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 600));

      await pumpAnalytics(tester, find.text('Advanced Account Analyst'));
      expect(find.text('Advanced Account Analyst'), findsOneWidget);
      expect(find.text('Global analytics filters'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.tap(find.text('Global analytics filters'));
      await tester.pumpAndSettle();
      expect(find.text('Apply filters'), findsOneWidget);
      expect(find.text('Last 90 days'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('Chinese performance labels render on a tablet', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(1024, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final repository = TradingRepository(firebaseEnabled: false);
      const user = AppUser(
        uid: 'performance-zh',
        email: 'zh@example.com',
        permissionOverrides: {AppPermission.statisticsReadOwn: true},
      );
      await repository.upsertJournal(
        _trade('zh-trade', profit: 50, userId: user.uid),
      );

      await tester.pumpWidget(
        _app(user, repository, locale: const Locale('zh')),
      );
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 600));

      await pumpAnalytics(tester, find.text('高级账户分析'));
      expect(find.text('高级账户分析'), findsOneWidget);
      expect(find.text('账户概览'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });
}

Widget _app(
  AppUser user,
  TradingRepository repository, {
  Locale locale = const Locale('en'),
}) => MaterialApp(
  locale: locale,
  theme: ThemeData.light(useMaterial3: true),
  darkTheme: ThemeData.dark(useMaterial3: true),
  supportedLocales: AppLocalizations.supportedLocales,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  home: StatisticsScreen(user: user, repository: repository),
);

TradingJournalEntry _trade(
  String id, {
  int day = 1,
  double profit = 0,
  String status = 'closed',
  String type = 'trade',
  String userId = 'performance-user',
  TradingRecordSource source = TradingRecordSource.manual,
  String? account,
}) {
  final opened = DateTime(2026, 7, day, 9);
  return TradingJournalEntry(
    id: id,
    userId: userId,
    type: type,
    source: source,
    tradingAccountId: account,
    status: status,
    openDate: opened,
    closeDate: opened.add(const Duration(hours: 2)),
    pair: 'XAUUSD',
    direction: 1,
    entryPrice: 3300,
    exitPrice: 3305,
    lotSize: .1,
    pips: profit,
    profitLoss: profit,
    result: profit > 0
        ? 1
        : profit < 0
        ? 2
        : 3,
    psychologyNotes: 'Calm',
    confidenceLevel: 8,
    disciplineRating: 8,
    strategyName: 'Breakout',
    followedTradingPlan: true,
    followedRiskRules: true,
    followedSlRule: true,
    followedTpRule: true,
    followedSessionRules: true,
  );
}

CapitalTransaction _capital(String id, double amount, int type) =>
    CapitalTransaction(
      id: id,
      userId: 'performance-user',
      depositDate: DateTime(2026, 7),
      amount: amount,
      type: type,
    );
