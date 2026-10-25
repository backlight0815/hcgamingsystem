import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'helpers/pump_analytics.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/security/permission_service.dart';
import 'package:hc_trader/features/dashboard/dashboard_screen.dart';
import 'package:hc_trader/features/journal/widgets/trading_report_workspace.dart';
import 'package:hc_trader/features/statistics/statistics_screen.dart';
import 'package:hc_trader/features/statistics/statistics_view_model.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/models/capital_transaction.dart';
import 'package:hc_trader/models/trading_journal_entry.dart';
import 'package:hc_trader/repositories/trading_repository.dart';
import 'package:hc_trader/services/trading_report_service.dart';
import 'package:hc_trader/shared/analytics/analytics_performance_chart.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('Analytics redesign data integrity', () {
    test('cash flow and non-closed records do not affect trading result', () {
      final report = TradingReportService().build(
        journals: [
          _trade('win', profit: 125),
          _trade('loss', day: 2, profit: -40),
          _trade('open', day: 3, profit: 900, status: 'open'),
        ],
        capitals: [
          _capital('deposit', amount: 10000, type: 1),
          _capital('withdrawal', amount: -2500, type: 2),
        ],
      );

      expect(report.summary.netProfitLoss, 85);
      expect(report.summary.totalTrades, 2);
      expect(report.capital.totalDeposits, 10000);
      expect(report.capital.totalWithdrawals, 2500);
      expect(report.summary.currentBalance, 7585);
    });

    test('statistics view model derives discipline outside the UI layer', () {
      final report = TradingReportService().build(
        journals: [
          _trade(
            'compliant',
            profit: 50,
            followedPlan: true,
            followedRisk: true,
            followedStop: true,
            followedSession: true,
          ),
          _trade('exception', day: 2, profit: -20),
        ],
        capitals: const [],
      );
      final model = StatisticsViewModel.fromReport(report);

      expect(model.planCompliance, 50);
      expect(model.riskCompliance, 50);
      expect(model.stopLossViolations.map((trade) => trade.id), ['exception']);
      expect(model.sessionViolations.map((trade) => trade.id), ['exception']);
    });
  });

  group('Analytics redesign responsive widgets', () {
    testWidgets('report workspace fits a small phone and changes chapter', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(360, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final report = TradingReportService().build(
        journals: [
          _trade('one', profit: 125, followedPlan: true, followedRisk: true),
          _trade('two', day: 2, profit: -30),
        ],
        capitals: [_capital('deposit', amount: 1000, type: 1)],
      );

      await tester.pumpWidget(
        _app(
          TradingReportWorkspace(
            report: report,
            rawRecordCount: 2,
            rawCapitalCount: 1,
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Trading Journal Report'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.drag(find.text('Overview'), const Offset(-420, 0));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Risk'));
      await tester.pumpAndSettle();
      await tester.drag(find.byType(ListView).first, const Offset(0, -450));
      await tester.pumpAndSettle();
      expect(find.text('Risk controls'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('report export exposes a staged and duplicate-safe state', (
      tester,
    ) async {
      final report = TradingReportService().build(
        journals: [_trade('one', profit: 25)],
        capitals: const [],
      );
      await tester.pumpWidget(
        _app(
          TradingReportWorkspace(
            report: report,
            rawRecordCount: 1,
            rawCapitalCount: 0,
            exporting: true,
            exportStage: 'Validating report account',
          ),
        ),
      );
      await tester.pump();

      expect(find.text('Validating report account'), findsOneWidget);
      expect(
        find.text(
          'Keep this screen open. Duplicate export requests are disabled.',
        ),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    });

    testWidgets('report workspace localizes new analytics copy to Chinese', (
      tester,
    ) async {
      final report = TradingReportService().build(
        journals: [_trade('one', profit: 25)],
        capitals: const [],
      );
      await tester.pumpWidget(
        _app(
          TradingReportWorkspace(
            report: report,
            rawRecordCount: 1,
            rawCapitalCount: 0,
          ),
          locale: const Locale('zh'),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('交易日志报告'), findsOneWidget);
      expect(find.text('概览'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('interactive performance chart supports large text', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(800, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final report = TradingReportService().build(
        journals: [
          _trade('one', profit: 25),
          _trade('two', day: 2, profit: -10),
          _trade('three', day: 3, profit: 40),
        ],
        capitals: const [],
      );

      await tester.pumpWidget(
        _app(
          MediaQuery(
            data: const MediaQueryData(
              size: Size(800, 900),
              textScaler: TextScaler.linear(1.5),
            ),
            child: Scaffold(
              body: SingleChildScrollView(
                child: AnalyticsPerformanceChart(dailyStats: report.dailyStats),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Net P/L'), findsOneWidget);
      expect(find.text('Drawdown'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets(
      'home command centre renders real local data on a small phone',
      (tester) async {
        await tester.binding.setSurfaceSize(const Size(390, 844));
        addTearDown(() => tester.binding.setSurfaceSize(null));
        final repository = TradingRepository(firebaseEnabled: false);
        const user = AppUser(
          uid: 'analytics-user',
          email: 'trader@example.com',
          displayName: 'Alex Trader',
          permissionOverrides: {AppPermission.statisticsReadOwn: true},
        );
        await repository.upsertJournal(
          _trade('home-trade', profit: 75, userId: user.uid),
        );
        await repository.upsertCapitalTransaction(
          _capital('home-deposit', amount: 1000, type: 1, userId: user.uid),
        );

        await tester.pumpWidget(
          _app(
            Scaffold(
              body: DashboardScreen(user: user, repository: repository),
            ),
          ),
        );
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 500));

        expect(find.textContaining('Alex Trader'), findsOneWidget);
        await tester.scrollUntilVisible(
          find.text("Today's trading snapshot"),
          250,
        );
        expect(find.text("Today's trading snapshot"), findsOneWidget);
        expect(tester.takeException(), isNull);
      },
    );

    testWidgets('statistics workspace renders real data on a dark tablet', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(1024, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final repository = TradingRepository(firebaseEnabled: false);
      const user = AppUser(
        uid: 'statistics-user',
        email: 'statistics@example.com',
        displayName: 'Statistics Trader',
        permissionOverrides: {AppPermission.statisticsReadOwn: true},
      );
      await repository.upsertJournal(
        _trade('statistics-trade', profit: 90, userId: user.uid),
      );

      await tester.pumpWidget(
        _app(
          StatisticsScreen(user: user, repository: repository),
          themeMode: ThemeMode.dark,
        ),
      );
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 600));

      expect(find.text('Trading Statistics'), findsOneWidget);
      await pumpAnalytics(tester, find.text('Advanced Account Analyst'));
      expect(find.text('Advanced Account Analyst'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });
}

Widget _app(
  Widget home, {
  Locale locale = const Locale('en'),
  ThemeMode themeMode = ThemeMode.light,
}) {
  return MaterialApp(
    locale: locale,
    theme: ThemeData.light(useMaterial3: true),
    darkTheme: ThemeData.dark(useMaterial3: true),
    themeMode: themeMode,
    supportedLocales: AppLocalizations.supportedLocales,
    localizationsDelegates: const [
      AppLocalizations.delegate,
      GlobalMaterialLocalizations.delegate,
      GlobalWidgetsLocalizations.delegate,
      GlobalCupertinoLocalizations.delegate,
    ],
    home: home,
  );
}

TradingJournalEntry _trade(
  String id, {
  int day = 1,
  double profit = 0,
  String status = 'closed',
  String userId = 'user-a',
  bool followedPlan = false,
  bool followedRisk = false,
  bool followedStop = false,
  bool followedSession = false,
}) {
  final open = DateTime(2026, 7, day, 9);
  return TradingJournalEntry(
    id: id,
    userId: userId,
    openDate: open,
    closeDate: open.add(const Duration(hours: 2)),
    pair: 'EURUSD',
    direction: 1,
    entryPrice: 1.1,
    exitPrice: 1.11,
    lotSize: .1,
    pips: profit,
    profitLoss: profit,
    result: profit > 0
        ? 1
        : profit < 0
        ? 2
        : 3,
    status: status,
    notes: 'Reviewed trade',
    psychologyNotes: 'Calm and focused',
    confidenceLevel: 8,
    disciplineRating: 8,
    strategyName: 'Breakout',
    followedTradingPlan: followedPlan,
    followedRiskRules: followedRisk,
    followedSlRule: followedStop,
    followedSessionRules: followedSession,
  );
}

CapitalTransaction _capital(
  String id, {
  required double amount,
  required int type,
  String userId = 'user-a',
}) {
  return CapitalTransaction(
    id: id,
    userId: userId,
    depositDate: DateTime(2026, 7),
    amount: amount,
    type: type,
  );
}
