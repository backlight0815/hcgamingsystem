import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/security/access_policy.dart';
import 'package:hc_trader/features/trading_analytics/data/analytics_trade_adapter.dart';
import 'package:hc_trader/features/trading_analytics/domain/analytics_filter.dart';
import 'package:hc_trader/features/trading_analytics/domain/analytics_result.dart';
import 'package:hc_trader/features/trading_analytics/domain/analytics_trade.dart';
import 'package:hc_trader/features/trading_analytics/domain/trading_analytics_engine.dart';
import 'package:hc_trader/features/trading_analytics/presentation/advanced_account_analyst_section.dart';
import 'package:hc_trader/features/trading_analytics/presentation/analytics_filter_bar.dart';
import 'package:hc_trader/models/access_status.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/models/capital_transaction.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('normalized advanced trading analytics', () {
    test('calculates trading results separately from account cash flow', () {
      final result = _analyze();
      final summary = result.summary;

      // Closed net P/L: 100 - 40 + 60 + 0 = 120.
      // Cash flow: +500 - 100 = +400, so actual balance is 1,520.
      expect(summary.netTradingPnl, 120);
      expect(summary.startingBalance, 1000);
      expect(summary.currentBalance, 1520);
      expect(summary.currentEquity, 1545);
      expect(result.tradingBalanceCurve.last.value, 1120);
      expect(result.actualBalanceCurve.last.value, 1520);
      expect(result.cumulativePnl.last.value, 120);
      expect(result.dataQuality.deposits, 1);
      expect(result.dataQuality.withdrawals, 1);
    });

    test('excludes open and cancelled records from outcome calculations', () {
      final result = _analyze();
      final summary = result.summary;

      expect(summary.totalClosedTrades, 4);
      expect(summary.totalOpenTrades, 1);
      expect(summary.winningTrades, 2);
      expect(summary.losingTrades, 1);
      expect(summary.breakevenTrades, 1);
      expect(summary.winRate, closeTo(66.6667, .001));
      expect(summary.lossRate, closeTo(33.3333, .001));
      expect(summary.averageTrade, 30);
      expect(summary.averageWin, 80);
      expect(summary.averageLoss, 40);
      expect(summary.expectancy, 40);
      expect(result.dataQuality.cancelledOrders, 1);
    });

    test(
      'uses gross results for profit factor and net results for expectancy',
      () {
        final summary = _analyze().summary;

        // Gross profit is 110 + 65; gross loss is 35.
        expect(summary.grossProfit, 175);
        expect(summary.grossLoss, 35);
        expect(summary.profitFactor, 5);
        expect(summary.payoffRatio, 2);
      },
    );

    test(
      'deduplicates cross-source position records using stable identity',
      () {
        final result = _analyze();

        expect(result.dataQuality.totalRawRecords, 7);
        expect(result.dataQuality.duplicateRecordsIgnored, 1);
        expect(
          result.trades.where((trade) => trade.sourcePositionId == 'P-1'),
          hasLength(1),
        );
        expect(
          result.trades
              .singleWhere((trade) => trade.sourcePositionId == 'P-1')
              .source,
          AnalyticsTradeDataSource.mt5,
        );
      },
    );

    test('calculates recovered drawdown and overlapping lot exposure', () {
      final summary = _analyze().summary;
      final drawdown = _analyze().maximumDrawdownPeriod;

      expect(summary.maximumDrawdown, 40);
      expect(drawdown.recovered, isTrue);
      expect(drawdown.peakDateUtc, DateTime.utc(2026, 7, 1, 10));
      expect(drawdown.troughDateUtc, DateTime.utc(2026, 7, 2, 10));
      expect(drawdown.recoveryDateUtc, DateTime.utc(2026, 7, 3, 10));
      expect(summary.maximumSimultaneousLots, closeTo(3.75, .0001));
      expect(summary.maximumBuyExposure, closeTo(.75, .0001));
      expect(summary.maximumSellExposure, closeTo(3, .0001));
    });

    test('builds time aggregations, histograms, holdings, and breakdowns', () {
      final result = _analyze();

      expect(result.dailyPnl, hasLength(4));
      expect(result.weeklyPnl, hasLength(1));
      expect(result.monthlyPnl, hasLength(1));
      expect(
        result.pnlHistogram.fold<int>(0, (sum, bin) => sum + bin.count),
        4,
      );
      expect(
        result.holdingHistogram.fold<int>(0, (sum, bin) => sum + bin.count),
        4,
      );
      expect(
        result.breakdowns['symbol']!.map((row) => row.label),
        containsAll(['XAUUSD', 'EURUSD', 'GBPUSD']),
      );
      expect(
        result.breakdowns['strategy']!.map((row) => row.label),
        contains('Breakout'),
      );
      expect(
        result.breakdowns['session']!.map((row) => row.label),
        contains('London'),
      );
      expect(
        result.breakdowns['source']!.map((row) => row.label),
        containsAll(['mt5', 'excel', 'manual']),
      );
    });

    test(
      'applies source, account, symbol, direction, and outcome globally',
      () {
        final result = TradingAnalyticsEngine().analyze(
          trades: _trades(),
          cashFlows: _cashFlows(),
          configuredStartingBalance: 1000,
          filter: const AnalyticsFilter(
            sources: {AnalyticsTradeDataSource.excel},
            accountId: 'account-a',
            symbol: 'EURUSD',
            direction: AnalyticsTradeDirection.sell,
            outcome: AnalyticsOutcomeFilter.losing,
          ),
        );

        expect(result.closedTrades, hasLength(1));
        expect(result.closedTrades.single.id, 'loss');
        expect(result.summary.netTradingPnl, -40);
        expect(result.dailyPnl, hasLength(1));
        expect(result.breakdowns['symbol']!.single.label, 'EURUSD');
      },
    );

    test('uses configured timezone offset for trading-day buckets', () {
      final trades = [
        _trade(
          id: 'utc-a',
          open: DateTime.utc(2026, 7, 1, 22),
          close: DateTime.utc(2026, 7, 1, 23, 30),
          net: 10,
        ),
        _trade(
          id: 'utc-b',
          open: DateTime.utc(2026, 7, 2, 1),
          close: DateTime.utc(2026, 7, 2, 2),
          net: 20,
        ),
      ];
      final result = TradingAnalyticsEngine().analyze(
        trades: trades,
        cashFlows: const [],
        filter: const AnalyticsFilter(),
        timezoneOffsetMinutes: 480,
      );

      expect(result.dailyPnl, hasLength(1));
      expect(result.dailyPnl.single.timestampUtc, DateTime.utc(2026, 7, 2));
      expect(result.dailyPnl.single.value, 30);
    });

    test('does not combine monetary totals across account currencies', () {
      final trades = [
        _trade(id: 'usd', net: 100, currency: 'USD', account: 'usd-account'),
        _trade(
          id: 'sgd',
          net: 200,
          currency: 'SGD',
          account: 'sgd-account',
          open: DateTime.utc(2026, 7, 2, 9),
          close: DateTime.utc(2026, 7, 2, 10),
        ),
      ];
      final result = TradingAnalyticsEngine().analyze(
        trades: trades,
        cashFlows: const [],
        filter: const AnalyticsFilter(),
      );

      expect(result.monetaryMetricsAvailable, isFalse);
      expect(result.currency, isEmpty);
      expect(result.currencyTotals, hasLength(2));
      expect(
        result.currencyTotals
            .singleWhere((item) => item.currency == 'USD')
            .netTradingPnl,
        100,
      );
      expect(
        result.currencyTotals
            .singleWhere((item) => item.currency == 'SGD')
            .netTradingPnl,
        200,
      );
    });

    test('reports no-loss and missing-risk conditions without infinity', () {
      final result = TradingAnalyticsEngine().analyze(
        trades: [
          _trade(id: 'win-a', net: 10),
          _trade(
            id: 'win-b',
            net: 20,
            open: DateTime.utc(2026, 7, 2, 9),
            close: DateTime.utc(2026, 7, 2, 10),
          ),
        ],
        cashFlows: const [],
        filter: const AnalyticsFilter(),
      );

      expect(result.summary.profitFactor, isNull);
      expect(result.summary.averageRMultiple, isNull);
      expect(result.rollingProfitFactor20.last.value, 10);
      expect(result.rollingProfitFactor20.last.label, 'no-losing-trades');
    });

    test('uses real equity snapshots only', () {
      final snapshots = [
        AccountEquitySnapshot(
          accountId: 'account-a',
          currency: 'USD',
          timestampUtc: _snapshotTime,
          balance: 1100,
          equity: 1075,
          floatingProfit: -25,
          margin: 100,
          freeMargin: 975,
          marginLevel: 1075,
        ),
      ];
      final result = TradingAnalyticsEngine().analyze(
        trades: _trades(),
        cashFlows: _cashFlows(),
        filter: const AnalyticsFilter(accountId: 'account-a'),
        equitySnapshots: snapshots,
      );
      final withoutSnapshots = TradingAnalyticsEngine().analyze(
        trades: _trades(),
        cashFlows: _cashFlows(),
        filter: const AnalyticsFilter(accountId: 'account-a'),
      );

      expect(result.equityCurve, hasLength(1));
      expect(result.equityCurve.single.value, 1075);
      expect(result.equityCurve.single.secondaryValue, 1100);
      expect(withoutSnapshots.equityCurve, isEmpty);
    });

    test('capital adapter keeps manual account ownership and source', () {
      const adapter = AnalyticsTradeAdapter();
      final flow = adapter
          .fromCapitals(
            [
              CapitalTransaction(
                id: 'manual-deposit',
                userId: 'owner-a',
                tradingAccountId: 'manual-account',
                depositDate: DateTime.utc(2026, 7, 1),
                amount: 500,
                type: 1,
              ),
            ],
            currencyByAccount: const {'manual-account': 'SGD'},
          )
          .single;

      expect(flow.accountId, 'manual-account');
      expect(flow.source, AnalyticsTradeDataSource.manual);
      expect(flow.currency, 'SGD');
    });
  });

  group('advanced analytics access and widgets', () {
    test('statistics is public to unapproved and guest-mode users', () {
      const unapproved = AppUser(
        uid: 'unapproved',
        email: 'unapproved@example.com',
        accessStatus: AccessStatus.notSubmitted,
      );
      const guest = AppUser(uid: 'guest-analytics', email: '', isDemo: true);

      expect(
        ClientAccessPolicy.canAccess(unapproved, AppFeatureArea.statistics),
        isTrue,
      );
      expect(
        ClientAccessPolicy.canAccess(guest, AppFeatureArea.statistics),
        isTrue,
      );
      expect(
        ClientAccessPolicy.canAccess(unapproved, AppFeatureArea.accounts),
        isFalse,
      );
    });

    testWidgets('analyst dashboard renders charts on a small phone', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(360, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));

      await tester.pumpWidget(
        _app(
          SingleChildScrollView(
            child: AdvancedAccountAnalystSection(result: _analyze()),
          ),
        ),
      );
      await tester.pump(const Duration(milliseconds: 500));

      expect(find.text('Account overview'), findsOneWidget);
      expect(find.text('Cumulative trading P/L'), findsOneWidget);
      expect(find.text('Balance versus equity'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('analyst dashboard localizes primary labels to Chinese', (
      tester,
    ) async {
      await tester.pumpWidget(
        _app(
          SingleChildScrollView(
            child: AdvancedAccountAnalystSection(result: _analyze()),
          ),
          locale: const Locale('zh'),
        ),
      );
      await tester.pump(const Duration(milliseconds: 300));

      expect(find.text('账户概览'), findsOneWidget);
      expect(find.text('累计交易损益'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('global filter sheet applies a data-source filter', (
      tester,
    ) async {
      AnalyticsFilter? changed;
      await tester.pumpWidget(
        _app(
          Scaffold(
            body: AnalyticsFilterBar(
              filter: const AnalyticsFilter(),
              accounts: const [
                AnalyticsAccountOption(
                  id: 'account-a',
                  label: 'Primary',
                  currency: 'USD',
                  source: AnalyticsTradeDataSource.manual,
                  isMain: true,
                ),
              ],
              trades: _trades(),
              canUseMt5: true,
              timezoneOffsetMinutes: 480,
              onChanged: (value) => changed = value,
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.byType(AnalyticsFilterBar));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Excel').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Apply filters'));
      await tester.pumpAndSettle();

      expect(changed?.sources, {AnalyticsTradeDataSource.excel});
      expect(tester.takeException(), isNull);
    });
  });
}

final _snapshotTime = DateTime.utc(2026, 7, 2, 12);

TradingAnalyticsResult _analyze() => TradingAnalyticsEngine().analyze(
  trades: _trades(),
  cashFlows: _cashFlows(),
  filter: const AnalyticsFilter(),
  configuredStartingBalance: 1000,
);

List<AnalyticsTrade> _trades() {
  final win = _trade(
    id: 'manual-win',
    account: 'account-a',
    source: AnalyticsTradeDataSource.manual,
    position: 'P-1',
    symbol: 'XAUUSD',
    direction: AnalyticsTradeDirection.buy,
    open: DateTime.utc(2026, 7, 1, 9),
    close: DateTime.utc(2026, 7, 1, 10),
    lots: .5,
    gross: 110,
    net: 100,
    commission: -5,
    swap: -2,
    fees: -3,
    strategy: 'Breakout',
    session: 'London',
    risk: 50,
  );
  return [
    win,
    _trade(
      id: 'mt5-duplicate',
      account: 'account-a',
      source: AnalyticsTradeDataSource.mt5,
      position: 'P-1',
      symbol: 'XAUUSD',
      direction: AnalyticsTradeDirection.buy,
      open: DateTime.utc(2026, 7, 1, 9),
      close: DateTime.utc(2026, 7, 1, 10),
      lots: .5,
      gross: 110,
      net: 100,
      commission: -5,
      swap: -2,
      fees: -3,
      strategy: 'Breakout',
      setup: 'A+',
      session: 'London',
      risk: 50,
    ),
    _trade(
      id: 'loss',
      account: 'account-a',
      source: AnalyticsTradeDataSource.excel,
      symbol: 'EURUSD',
      direction: AnalyticsTradeDirection.sell,
      open: DateTime.utc(2026, 7, 1, 9, 30),
      close: DateTime.utc(2026, 7, 2, 10),
      lots: 1,
      gross: -35,
      net: -40,
      commission: -5,
      strategy: 'Mean Reversion',
      session: 'New York',
      risk: 40,
    ),
    _trade(
      id: 'win-two',
      account: 'account-a',
      source: AnalyticsTradeDataSource.manual,
      symbol: 'GBPUSD',
      direction: AnalyticsTradeDirection.buy,
      open: DateTime.utc(2026, 7, 1, 9, 45),
      close: DateTime.utc(2026, 7, 3, 10),
      lots: .25,
      gross: 65,
      net: 60,
      commission: -5,
      strategy: 'Breakout',
      session: 'London',
      risk: 30,
    ),
    _trade(
      id: 'breakeven',
      account: 'account-a',
      source: AnalyticsTradeDataSource.manual,
      symbol: 'XAUUSD',
      direction: AnalyticsTradeDirection.sell,
      open: DateTime.utc(2026, 7, 4, 9, 30),
      close: DateTime.utc(2026, 7, 4, 10),
      lots: .2,
      net: 0,
      strategy: 'Breakout',
      session: 'Asia',
    ),
    _trade(
      id: 'open',
      account: 'account-a',
      source: AnalyticsTradeDataSource.mt5,
      symbol: 'USDJPY',
      direction: AnalyticsTradeDirection.sell,
      status: AnalyticsTradeStatus.open,
      open: DateTime.utc(2026, 7, 1, 9, 50),
      close: null,
      lots: 2,
      gross: 25,
      net: 25,
    ),
    _trade(
      id: 'cancelled',
      account: 'account-a',
      source: AnalyticsTradeDataSource.mt5,
      symbol: 'AUDUSD',
      direction: AnalyticsTradeDirection.buy,
      status: AnalyticsTradeStatus.cancelled,
      open: DateTime.utc(2026, 7, 1, 8),
      close: null,
      lots: 3,
      net: 500,
    ),
  ];
}

List<AnalyticsCashFlow> _cashFlows() => [
  AnalyticsCashFlow(
    id: 'deposit',
    userId: 'owner-a',
    accountId: 'account-a',
    currency: 'USD',
    timestampUtc: DateTime.utc(2026, 7, 1, 8),
    amount: 500,
    type: AnalyticsCashFlowType.deposit,
  ),
  AnalyticsCashFlow(
    id: 'withdrawal',
    userId: 'owner-a',
    accountId: 'account-a',
    currency: 'USD',
    timestampUtc: DateTime.utc(2026, 7, 3, 12),
    amount: 100,
    type: AnalyticsCashFlowType.withdrawal,
  ),
];

AnalyticsTrade _trade({
  required String id,
  DateTime? open,
  DateTime? close,
  String account = 'account-a',
  String currency = 'USD',
  String symbol = 'XAUUSD',
  AnalyticsTradeDirection direction = AnalyticsTradeDirection.buy,
  AnalyticsTradeStatus status = AnalyticsTradeStatus.closed,
  AnalyticsTradeDataSource source = AnalyticsTradeDataSource.manual,
  double lots = .1,
  double gross = 0,
  double net = 0,
  double commission = 0,
  double swap = 0,
  double fees = 0,
  double? risk,
  String? strategy,
  String? setup,
  String? session,
  String? position,
}) {
  final opened = open ?? DateTime.utc(2026, 7, 1, 9);
  return AnalyticsTrade(
    id: id,
    userId: 'owner-a',
    accountId: account,
    accountCurrency: currency,
    symbol: symbol,
    direction: direction,
    status: status,
    openTimeUtc: opened,
    closeTimeUtc:
        close ??
        (status == AnalyticsTradeStatus.closed
            ? opened.add(const Duration(hours: 1))
            : null),
    entryPrice: 1,
    exitPrice: status == AnalyticsTradeStatus.closed ? 1.1 : null,
    lots: lots,
    grossProfit: gross == 0 && net != 0 ? net : gross,
    commission: commission,
    swap: swap,
    fees: fees,
    netProfit: net,
    initialRiskAmount: risk,
    strategyName: strategy,
    setupName: setup,
    session: session,
    source: source,
    sourcePositionId: position,
  );
}

Widget _app(Widget child, {Locale locale = const Locale('en')}) => MaterialApp(
  locale: locale,
  theme: ThemeData.light(useMaterial3: true),
  supportedLocales: AppLocalizations.supportedLocales,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  home: Scaffold(body: child),
);
