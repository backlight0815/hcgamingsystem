import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:hc_trader/features/prop_firm/prop_firm_screen.dart';
import 'package:hc_trader/features/performance/performance_rating_screen.dart';
import 'package:hc_trader/features/psychology/psychology_screen.dart';
import 'package:hc_trader/features/risk/risk_calculator_screen.dart';
import 'package:hc_trader/features/statistics/statistics_screen.dart';
import 'package:hc_trader/features/tools/toolkit_models.dart';
import 'package:hc_trader/features/tools/toolkit_widgets.dart';
import 'package:hc_trader/features/tools/tools_screen.dart';
import 'package:hc_trader/features/trading_plan/trading_plan_screen.dart';
import 'package:hc_trader/features/trading_plan/trading_strategy_screen.dart';
import 'package:hc_trader/models/access_status.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/models/trading_data_source.dart';
import 'package:hc_trader/repositories/trading_repository.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('toolkit filtering and persistence', () {
    test('toolkit workspace text is fully localized in Chinese', () {
      const localizations = AppLocalizations(Locale('zh'));
      final keys = <String>{
        for (final item in toolkitCatalog) ...{
          item.titleKey,
          item.descriptionKey,
          item.searchKeywordsKey,
          item.actionKey,
          item.category.titleKey,
          item.category.filterKey,
        },
        'Prepare, analyze and improve your trading from one focused workspace.',
        'Search trading tools',
        'Search toolkit',
        '{count} tools available',
        'Main MT5 account data',
        'Manual trading data',
        'Risk setting: {risk}%',
        'Main MT5 account setup required',
        'Quick Actions',
        'Recently Used',
        'Recommended Next',
        'Explore Tools',
        'No matching tools',
        'Latest risk setting',
        'No saved calculation',
        'Selected data source',
        'Setup required',
        'Tracking source',
        'No selected main MT5 account',
        "Today's check-in",
        '{score}/100 · {status}',
        'Active trading plan',
        'No active trading plan',
        'Active trading strategy',
        'No trading strategy configured',
      };

      for (final key in keys) {
        expect(
          localizations.text(key),
          isNot(key),
          reason: 'Missing Chinese translation for "$key"',
        );
      }
    });

    test('search matches English and localized Chinese keywords', () {
      const english = AppLocalizations(Locale('en'));
      const chinese = AppLocalizations(Locale('zh'));

      expect(
        filterToolkitItems(
          items: toolkitCatalog,
          localizations: english,
          query: 'drawdown',
        ).map((item) => item.id),
        containsAll(['statistics', 'prop_firm']),
      );
      expect(
        filterToolkitItems(
          items: toolkitCatalog,
          localizations: chinese,
          query: '仓位',
        ).map((item) => item.id),
        contains('risk_calculator'),
      );
    });

    test('recent tools are unique, newest-first, and capped at four', () async {
      SharedPreferences.setMockInitialValues({});
      final storage = await LocalAppStorage.create();
      var minute = 0;
      final recentStore = ToolkitRecentStore(
        storage,
        now: () => DateTime(2026, 7, 12, 10, minute++),
      );

      for (final id in const [
        'risk_calculator',
        'statistics',
        'psychology',
        'trading_plan',
        'risk_calculator',
      ]) {
        await recentStore.record('user-1', id);
      }

      expect(recentStore.load('user-1').map((entry) => entry.toolId), [
        'risk_calculator',
        'trading_plan',
        'psychology',
        'statistics',
      ]);
    });

    test(
      'dashboard summary uses local saved setup without fake values',
      () async {
        SharedPreferences.setMockInitialValues({});
        final storage = await LocalAppStorage.create();
        final now = DateTime(2026, 7, 12, 9, 30);
        await storage.writeMap('user-1', 'trading_data_display_mode', {
          'mode': 'mt5Only',
        });
        await storage.writeMap('user-1', 'last_risk_calculation', {
          'risk_percent': 0.75,
          'symbol': 'XAUUSD',
        });
        await storage.writeList('user-1', 'psychology_checkins', [
          {
            'id': 'check-1',
            'created_at': now.toIso8601String(),
            'emotion': 'Calm',
            'confidence': 7,
            'discipline': 8,
            'fomo': false,
            'revenge_urge': false,
            'overconfidence': false,
          },
        ]);
        await storage.writeList('user-1', 'trading_plans', [
          {
            'id': 'plan-1',
            'plan': {
              'plan_name': 'London Plan',
              'is_default': true,
              'risk_percent': 0.75,
              'minimum_risk_reward': 2,
            },
            'created_at': now.toIso8601String(),
            'updated_at': now.toIso8601String(),
          },
        ]);
        await storage.writeList('user-1', 'trading_strategies', [
          {
            'id': 'strategy-1',
            'strategy': {
              'name': 'Breakout Retest',
              'trading_style': 'Intraday',
              'risk_reward_target': '1:2',
              'is_active': true,
            },
            'created_at': now.toIso8601String(),
            'updated_at': now.toIso8601String(),
          },
        ]);

        final snapshot = ToolkitDashboardService(
          now: () => now,
        ).load(storage, 'user-1');

        expect(snapshot.dataMode, TradingDataDisplayMode.mt5Only);
        expect(snapshot.riskPercent, 0.75);
        expect(snapshot.riskSymbol, 'XAUUSD');
        expect(snapshot.psychologyCheckedToday, isTrue);
        expect(snapshot.psychologyScore, isNotNull);
        expect(snapshot.planName, 'London Plan');
        expect(snapshot.strategyName, 'Breakout Retest');
        expect(snapshot.strategyRiskReward, '1:2');
      },
    );

    test(
      'missing local setup remains absent instead of becoming zero data',
      () async {
        SharedPreferences.setMockInitialValues({});
        final storage = await LocalAppStorage.create();

        final snapshot = const ToolkitDashboardService().load(
          storage,
          'user-1',
        );

        expect(snapshot.dataMode, TradingDataDisplayMode.manualOnly);
        expect(snapshot.riskPercent, isNull);
        expect(snapshot.psychologyScore, isNull);
        expect(snapshot.hasPlan, isFalse);
        expect(snapshot.hasStrategy, isFalse);
      },
    );

    test(
      'one malformed cached record does not break other summaries',
      () async {
        SharedPreferences.setMockInitialValues({});
        final storage = await LocalAppStorage.create();
        await storage.writeMap('user-1', 'last_risk_calculation', {
          'risk_percent': 1,
        });
        await storage.writeList('user-1', 'trading_plans', [
          {'id': 'broken', 'plan': 'not-a-map'},
        ]);

        final snapshot = const ToolkitDashboardService().load(
          storage,
          'user-1',
        );

        expect(snapshot.riskPercent, 1);
        expect(snapshot.hasPlan, isFalse);
      },
    );
  });

  group('toolkit workspace widgets', () {
    testWidgets('page loads and search and category filters work', (
      tester,
    ) async {
      final harness = await _pumpTools(tester);
      addTearDown(harness.dispose);

      expect(find.byType(ToolkitHero), findsOneWidget);
      expect(
        find.byKey(const ValueKey('tool-risk_calculator')),
        findsOneWidget,
      );
      expect(find.byKey(const ValueKey('tool-statistics')), findsOneWidget);

      await tester.enterText(find.byType(SearchBar), 'position size');
      await tester.pumpAndSettle();
      expect(
        find.byKey(const ValueKey('tool-risk_calculator')),
        findsOneWidget,
      );
      expect(find.byKey(const ValueKey('tool-statistics')), findsNothing);

      await tester.enterText(find.byType(SearchBar), 'not-a-tool');
      await tester.pumpAndSettle();
      expect(find.text('No matching tools'), findsOneWidget);

      await tester.enterText(find.byType(SearchBar), '');
      await tester.tap(find.widgetWithText(ChoiceChip, 'Performance'));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('tool-statistics')), findsOneWidget);
      expect(find.byKey(const ValueKey('tool-risk_calculator')), findsNothing);
    });

    testWidgets('restricted users receive public but not client-only tools', (
      tester,
    ) async {
      final harness = await _pumpTools(
        tester,
        user: const AppUser(uid: 'restricted', email: 'r@example.com'),
      );
      addTearDown(harness.dispose);

      expect(
        find.byKey(const ValueKey('tool-risk_calculator')),
        findsOneWidget,
      );
      expect(find.byKey(const ValueKey('tool-psychology')), findsOneWidget);
      expect(find.byKey(const ValueKey('tool-statistics')), findsOneWidget);
      expect(
        find.byKey(const ValueKey('tool-performance_ratings')),
        findsNothing,
      );
      expect(find.byKey(const ValueKey('tool-prop_firm')), findsNothing);
    });

    testWidgets('all toolkit tiles preserve their existing destinations', (
      tester,
    ) async {
      final harness = await _pumpTools(tester);
      addTearDown(harness.dispose);
      final routes = <String, Type>{
        'risk_calculator': RiskCalculatorScreen,
        'statistics': StatisticsScreen,
        'performance_ratings': PerformanceRatingScreen,
        'prop_firm': PropFirmScreen,
        'psychology': PsychologyScreen,
        'trading_plan': TradingPlanScreen,
        'trading_strategies': TradingStrategyScreen,
      };

      for (final route in routes.entries) {
        final tile = find.byKey(ValueKey('tool-${route.key}'));
        await tester.ensureVisible(tile);
        await tester.tap(tile);
        await tester.pumpAndSettle();
        expect(find.byType(route.value), findsOneWidget, reason: route.key);
        await tester.pageBack();
        await tester.pumpAndSettle();
      }
    });

    testWidgets('recent tools render newest first', (tester) async {
      SharedPreferences.setMockInitialValues({});
      final storage = await LocalAppStorage.create();
      await storage.writeList('user-1', 'toolkit_recent_tools', [
        {'tool_id': 'psychology', 'opened_at': '2026-07-12T12:00:00.000'},
        {'tool_id': 'risk_calculator', 'opened_at': '2026-07-12T11:00:00.000'},
      ]);
      final harness = await _pumpTools(tester, storage: storage);
      addTearDown(harness.dispose);

      final recent = tester
          .widgetList<ToolkitRecentTool>(find.byType(ToolkitRecentTool))
          .map((widget) => widget.title)
          .toList();
      expect(recent.take(2), ['Psychology', 'Risk Calculator']);
    });

    for (final testCase in const [
      ('small mobile', Size(320, 900)),
      ('standard mobile', Size(412, 900)),
      ('tablet', Size(800, 1000)),
      ('desktop', Size(1280, 900)),
      ('narrow Windows', Size(560, 800)),
    ]) {
      testWidgets('${testCase.$1} layout has no overflow', (tester) async {
        final harness = await _pumpTools(
          tester,
          size: testCase.$2,
          locale: const Locale('zh'),
          textScaler: const TextScaler.linear(1.35),
        );
        addTearDown(harness.dispose);

        expect(find.text('交易工具箱'), findsWidgets);
        expect(tester.takeException(), isNull);
      });
    }
  });
}

class _ToolsHarness {
  const _ToolsHarness(this.tester);

  final WidgetTester tester;

  Future<void> dispose() async {
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.binding.setSurfaceSize(null);
  }
}

Future<_ToolsHarness> _pumpTools(
  WidgetTester tester, {
  LocalAppStorage? storage,
  AppUser user = const AppUser(
    uid: 'user-1',
    email: 'trader@example.com',
    accessStatus: AccessStatus.approved,
    mainMt5AccountKey: 'main-account',
  ),
  Size size = const Size(900, 1000),
  Locale locale = const Locale('en'),
  TextScaler textScaler = TextScaler.noScaling,
}) async {
  SharedPreferences.setMockInitialValues({});
  final appStorage = storage ?? await LocalAppStorage.create();
  final repository = TradingRepository(
    firebaseEnabled: false,
    storage: appStorage,
  );
  await tester.binding.setSurfaceSize(size);
  await tester.pumpWidget(
    MaterialApp(
      locale: locale,
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      builder: (context, child) => MediaQuery(
        data: MediaQuery.of(context).copyWith(textScaler: textScaler),
        child: child!,
      ),
      home: ToolsScreen(
        user: user,
        repository: repository,
        storage: appStorage,
      ),
    ),
  );
  await tester.pumpAndSettle();
  return _ToolsHarness(tester);
}
