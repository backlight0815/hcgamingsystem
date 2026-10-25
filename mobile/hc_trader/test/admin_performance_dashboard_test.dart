import 'package:excel/excel.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/features/admin/performance/admin_performance_controller.dart';
import 'package:hc_trader/features/admin/performance/admin_performance_detail_screen.dart';
import 'package:hc_trader/features/admin/performance/admin_performance_export_service.dart';
import 'package:hc_trader/features/admin/performance/admin_performance_models.dart';
import 'package:hc_trader/features/admin/performance/admin_performance_screen.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/repositories/trading_repository.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('admin performance calculations and state', () {
    test('summary and insights use filtered real values safely', () {
      final records = [
        AdminTraderPerformance.fromMap(
          _row(
            id: 'a',
            name: 'Alice',
            profit: 1200,
            growth: 12,
            drawdown: 4,
            previousGrowth: 8,
          ),
        ),
        AdminTraderPerformance.fromMap(
          _row(
            id: 'b',
            name: 'Bob',
            profit: -300,
            growth: -3,
            drawdown: 12,
            previousGrowth: 2,
            review: 'under_review',
          ),
        ),
      ];

      final summary = AdminPerformanceSummary.fromRecords(records);
      final insights = buildAdminPerformanceInsights(records);

      expect(summary.totalTraders, 2);
      expect(summary.profitableTraders, 1);
      expect(summary.losingTraders, 1);
      expect(summary.netProfit, 900);
      expect(summary.averageReturn, 4.5);
      expect(summary.reviewRequired, 1);
      expect(
        insights.map((item) => item.title),
        contains('Performance improved'),
      );
      expect(
        insights.map((item) => item.title),
        contains('Performance declined'),
      );
      expect(
        insights.map((item) => item.title),
        contains('High drawdown exposure'),
      );
      expect(safePercentChange(10, 0), 0);
      expect(clampFinite(double.infinity), 0);
    });

    test(
      'controller filters, sorts, paginates, and scopes remote requests',
      () async {
        final repository = _FakePerformanceRepository(
          rows: [
            for (var index = 0; index < 14; index++)
              _row(
                id: 'trader-$index',
                name: index == 0 ? 'Target Trader' : 'Trader $index',
                profit: index.isEven
                    ? (1000 - index).toDouble()
                    : -index.toDouble(),
                growth: index.toDouble(),
                drawdown: index.toDouble(),
                trades: index + 1,
              ),
          ],
        );
        final controller = AdminPerformanceController(repository: repository);
        addTearDown(controller.dispose);

        await controller.load();
        expect(controller.allRecords, hasLength(14));
        expect(repository.lastSource, 'all');
        expect(repository.lastStart, isNotNull);
        expect(controller.pageRecords, hasLength(10));
        controller.setPage(1);
        expect(controller.pageRecords, hasLength(4));

        controller.setLocalFilter(
          controller.filter.copyWith(
            profit: AdminPerformanceProfitFilter.profitable,
            minimumTrades: 5,
          ),
        );
        expect(
          controller.filteredRecords.every(
            (record) => record.netProfit > 0 && record.totalTrades >= 5,
          ),
          isTrue,
        );

        controller.setSort(AdminPerformanceSort.profitLow);
        final profits = controller.filteredRecords
            .map((record) => record.netProfit)
            .toList();
        expect(profits, orderedEquals([...profits]..sort()));

        controller.setLocalFilter(const AdminPerformanceFilter());
        controller.search('target');
        await Future<void>.delayed(const Duration(milliseconds: 400));
        expect(
          controller.filteredRecords.map((record) => record.name),
          contains('Target Trader'),
        );

        await controller.setRemoteFilter(
          const AdminPerformanceFilter(
            period: AdminPerformancePeriod.sevenDays,
            dataSource: 'mt5_sync',
          ),
        );
        expect(repository.lastSource, 'mt5_sync');
        expect(repository.lastStart, isNotNull);
      },
    );

    test(
      'controller exposes retryable error state without an endless load',
      () async {
        final controller = AdminPerformanceController(
          repository: _FakePerformanceRepository(error: StateError('offline')),
        );
        addTearDown(controller.dispose);

        await controller.load();

        expect(controller.loading, isFalse);
        expect(controller.refreshing, isFalse);
        expect(controller.error, isA<StateError>());
      },
    );

    test('Excel export contains localized headers and filtered rows', () {
      final records = _sampleRows.map(AdminTraderPerformance.fromMap).toList();
      final bytes = const AdminPerformanceExportService().buildExcelBytes(
        records: records,
        labels: const {
          'sheet': '表现',
          'rank': '排名',
          'trader': '交易员',
          'email': '邮箱',
        },
      );

      expect(bytes, isNotNull);
      final workbook = Excel.decodeBytes(bytes!);
      final sheet = workbook['表现'];
      expect(sheet.rows, hasLength(3));
      expect(sheet.rows.first.first?.value.toString(), '排名');
      expect(sheet.rows[1][1]?.value.toString(), 'Alice Trader');
    });
  });

  group('admin performance responsive dashboard', () {
    testWidgets('renders mobile cards at 360 px without exceptions', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(360, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));

      await tester.pumpWidget(
        _app(_FakePerformanceRepository(rows: _sampleRows)),
      );
      await tester.pumpAndSettle();

      expect(find.text('Performances'), findsOneWidget);
      expect(find.text('Search trader name or email'), findsOneWidget);
      expect(find.text('Alice Trader'), findsWidgets);
      expect(find.byType(DataTable), findsNothing);
      expect(tester.takeException(), isNull);
    });

    testWidgets('opens the mobile filter sheet without overflow', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(360, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));

      await tester.pumpWidget(
        _app(_FakePerformanceRepository(rows: _sampleRows)),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Filters'));
      await tester.pumpAndSettle();

      expect(find.text('Performance filters'), findsOneWidget);
      expect(find.text('Apply filters'), findsOneWidget);
      expect(find.text('Combined data'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('renders desktop sortable table and comparison control', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(1366, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));

      await tester.pumpWidget(
        _app(_FakePerformanceRepository(rows: _sampleRows)),
      );
      await tester.pumpAndSettle();

      expect(find.byType(DataTable), findsOneWidget);
      expect(find.text('Compare'), findsOneWidget);
      expect(find.text('Net profit'), findsWidgets);
      expect(find.text('Alice Trader'), findsWidgets);
      expect(tester.takeException(), isNull);
    });

    testWidgets('renders the trader detail experience on a small phone', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(360, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));

      await tester.pumpWidget(
        _localizedApp(
          AdminPerformanceDetailScreen(
            user: _admin,
            repository: _FakePerformanceRepository(rows: _sampleRows),
            record: AdminTraderPerformance.fromMap(_sampleRows.first),
            onReviewChanged: () {},
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Trader performance'), findsWidgets);
      expect(find.text('Performance metrics'), findsOneWidget);
      expect(find.text('Recent trade history'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('renders two compared traders with a shared scope', (
      tester,
    ) async {
      await tester.binding.setSurfaceSize(const Size(1366, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));

      await tester.pumpWidget(
        _localizedApp(
          AdminPerformanceComparisonScreen(
            records: _sampleRows.map(AdminTraderPerformance.fromMap).toList(),
            period: AdminPerformancePeriod.thirtyDays,
            dataSource: 'all',
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Performance comparison'), findsOneWidget);
      expect(find.text('Comparison scope'), findsOneWidget);
      expect(find.text('Net profit comparison'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('renders the dashboard in Chinese', (tester) async {
      await tester.binding.setSurfaceSize(const Size(1024, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));

      await tester.pumpWidget(
        _app(
          _FakePerformanceRepository(rows: _sampleRows),
          locale: const Locale('zh'),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('表现管理'), findsOneWidget);
      expect(find.text('搜索交易员姓名或邮箱'), findsOneWidget);
      expect(find.text('交易员表现'), findsOneWidget);
      expect(find.text('Performances'), findsNothing);
      expect(tester.takeException(), isNull);
    });

    testWidgets('has no overflow at representative responsive widths', (
      tester,
    ) async {
      const sizes = [
        Size(393, 852),
        Size(600, 960),
        Size(768, 1024),
        Size(1024, 768),
        Size(1280, 720),
        Size(1440, 900),
        Size(1920, 1080),
        Size(2560, 1080),
      ];
      addTearDown(() => tester.binding.setSurfaceSize(null));
      for (final size in sizes) {
        await tester.binding.setSurfaceSize(size);
        await tester.pumpWidget(
          _app(_FakePerformanceRepository(rows: _sampleRows)),
        );
        await tester.pumpAndSettle();
        expect(
          tester.takeException(),
          isNull,
          reason: 'Unexpected layout failure at $size',
        );
      }
    });

    testWidgets('supports increased text scaling on a phone', (tester) async {
      await tester.binding.setSurfaceSize(const Size(393, 852));
      addTearDown(() => tester.binding.setSurfaceSize(null));

      await tester.pumpWidget(
        _localizedApp(
          Scaffold(
            body: AdminPerformanceTab(
              user: _admin,
              repository: _FakePerformanceRepository(rows: _sampleRows),
            ),
          ),
          textScale: 1.4,
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Performances'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('does not request protected data for an unauthorized user', (
      tester,
    ) async {
      final repository = _FakePerformanceRepository(rows: _sampleRows);
      await tester.pumpWidget(
        _app(
          repository,
          user: const AppUser(uid: 'viewer', email: 'viewer@example.test'),
        ),
      );
      await tester.pump();

      expect(
        find.text('You do not have permission to view trader performance.'),
        findsOneWidget,
      );
      expect(repository.loadCount, 0);
    });
  });
}

const _admin = AppUser(
  uid: 'admin',
  email: 'admin@example.test',
  displayName: 'Admin',
  roleId: 1,
  isAdmin: true,
);

Widget _app(
  TradingRepository repository, {
  AppUser user = _admin,
  Locale locale = const Locale('en'),
}) => _localizedApp(
  Scaffold(
    body: AdminPerformanceTab(user: user, repository: repository),
  ),
  locale: locale,
);

Widget _localizedApp(
  Widget home, {
  Locale locale = const Locale('en'),
  double textScale = 1,
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
  builder: (context, child) => MediaQuery(
    data: MediaQuery.of(
      context,
    ).copyWith(textScaler: TextScaler.linear(textScale)),
    child: child!,
  ),
  home: home,
);

class _FakePerformanceRepository extends TradingRepository {
  _FakePerformanceRepository({this.rows = const [], this.error})
    : super(firebaseEnabled: false);

  final List<Map<String, dynamic>> rows;
  final Object? error;
  int loadCount = 0;
  DateTime? lastStart;
  DateTime? lastEnd;
  String? lastSource;

  @override
  Future<List<Map<String, dynamic>>> adminTraderPerformance({
    DateTime? startAt,
    DateTime? endAt,
    String dataSource = 'all',
  }) async {
    loadCount++;
    lastStart = startAt;
    lastEnd = endAt;
    lastSource = dataSource;
    if (error != null) throw error!;
    return rows;
  }
}

final _sampleRows = [
  _row(
    id: 'alice',
    name: 'Alice Trader',
    profit: 1250,
    growth: 12.5,
    drawdown: 3.5,
    previousGrowth: 8,
  ),
  _row(
    id: 'bob',
    name: 'Bob Trader',
    profit: -240,
    growth: -2.4,
    drawdown: 11,
    previousGrowth: 1,
    review: 'under_review',
  ),
];

Map<String, dynamic> _row({
  required String id,
  required String name,
  required double profit,
  required double growth,
  required double drawdown,
  double? previousGrowth,
  int trades = 20,
  String review = 'none',
}) => {
  'user': {
    'id': id,
    'name': name,
    'email': '$id@example.test',
    'account_status': 'active',
  },
  'rank': id == 'alice' ? 1 : 2,
  'total_trades': trades,
  'wins': (trades * .6).round(),
  'losses': (trades * .4).round(),
  'break_evens': 0,
  'net_profit_loss': profit,
  'growth_percent': growth,
  'win_rate': 60,
  'profit_factor': profit >= 0 ? 1.8 : .7,
  'profit_factor_label': profit >= 0 ? '1.80' : '0.70',
  'payoff_ratio': 1.4,
  'expectancy': trades == 0 ? 0 : profit / trades,
  'max_drawdown': drawdown * 100,
  'max_drawdown_percent': drawdown,
  'balance': 10000 + profit,
  'deposits': 10000,
  'withdrawals': 0,
  'report_score': profit >= 0 ? 82 : 38,
  'score': profit >= 0 ? 80 : 30,
  'report_grade': profit >= 0 ? 'Strong' : 'Needs Improvement',
  'prop_firm_review_status': review,
  'funded_status': profit >= 0 ? 1 : 0,
  'prop_firm_phase': 1,
  'data_sources': ['manual'],
  'last_trade_at': DateTime(2026, 7, 17).toIso8601String(),
  'previous_net_profit_loss': profit - 100,
  'previous_growth_percent': previousGrowth,
  'previous_win_rate': 55,
  'previous_max_drawdown_percent': drawdown + 1,
  'daily_stats': [
    {
      'date': DateTime(2026, 7, 16).toIso8601String(),
      'trades': 2,
      'profit_loss': profit * .4,
    },
    {
      'date': DateTime(2026, 7, 17).toIso8601String(),
      'trades': 3,
      'profit_loss': profit * .6,
    },
  ],
  'monthly_stats': [
    {
      'label': '2026-07',
      'trades': trades,
      'profit_loss': profit,
      'win_rate': 60,
    },
  ],
  'pair_stats': [
    {
      'label': 'XAUUSD',
      'trades': trades,
      'profit_loss': profit,
      'win_rate': 60,
    },
  ],
  'session_stats': [
    {
      'label': 'London',
      'trades': trades,
      'profit_loss': profit,
      'win_rate': 60,
    },
  ],
  'recent_trades': [
    {
      'id': '$id-trade',
      'pair': 'XAUUSD',
      'direction': 1,
      'close_date': DateTime(2026, 7, 17).toIso8601String(),
      'lot_size': .1,
      'profit_loss': profit,
      'pips': 25,
      'source': 'manual',
      'strategy': 'Breakout',
      'attachment_count': 0,
    },
  ],
};
