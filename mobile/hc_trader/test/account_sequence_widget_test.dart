import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/account_sequence_translations.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:hc_trader/features/multi_account_prop_firm/data/prop_firm_portfolio_repository.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/prop_firm_models.dart';
import 'package:hc_trader/features/multi_account_prop_firm/presentation/prop_firm_planner_controller.dart';
import 'package:hc_trader/features/multi_account_prop_firm/presentation/prop_firm_planner_screen.dart';
import 'package:hc_trader/features/multi_account_prop_firm/presentation/simplified_strategy_ui.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('sequence-specific English copy has Simplified Chinese coverage', () {
    const l10n = AppLocalizations(Locale('zh'));
    expect(accountSequenceZh.length, greaterThan(140));
    for (final key in const [
      'Sequence',
      'Dynamic Account Trading Sequence',
      'Choose the starting account',
      'Generated account sequence',
      'Confirm sequence',
      'Start sequence session',
      'Account health comparison',
      'Conditional decision tree',
      'Portfolio stop conditions',
      'Decision support, not trade execution',
      'Account is protected for payout.',
      'Reassess all portfolio loss and discipline limits.',
      'Understanding the 0–100 Health Score',
      'How does a profitable recovery improve my score?',
      'Why does a cooling account still show a health score?',
      'Visual answers explain the score, cooldowns, recovery and account eligibility.',
    ]) {
      expect(
        l10n.text(key),
        isNot(key),
        reason: 'Missing sequence translation: $key',
      );
    }
  });

  testWidgets(
    'desktop selects a starting account and starts it from the overview',
    (tester) async {
      final storage = await _starterStorage('sequence-widget');
      await tester.binding.setSurfaceSize(const Size(1280, 1050));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await _pumpPlanner(
        tester,
        storage,
        'sequence-widget',
        size: const Size(1280, 1050),
      );

      expect(find.byType(NavigationRail), findsOneWidget);
      expect(
        tester.widget<NavigationRail>(find.byType(NavigationRail)).destinations,
        hasLength(3),
      );
      final accountB = PropFirmPortfolioRepository(
        storage: storage,
        ownerId: 'sequence-widget',
      ).load()!.accounts[1];
      final selectB = find.byKey(Key('set-starting-${accountB.id}'));
      await tester.drag(find.byType(ListView).first, const Offset(0, -550));
      await tester.pumpAndSettle();
      await tester.tap(selectB);
      await tester.pumpAndSettle();
      expect(find.text('Recommended Next'), findsWidgets);
      await tester.drag(find.byType(ListView).first, const Offset(0, 1100));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('primary-trading-action')));
      await tester.pumpAndSettle();
      expect(find.text('Start Trading'), findsWidgets);
      await tester.tap(find.byKey(const Key('confirm-start-trading')));
      await tester.pumpAndSettle();
      expect(find.text('Record Trade Result'), findsWidgets);
      expect(find.text('Trading Now'), findsOneWidget);
      expect(find.text('Account B'), findsWidgets);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'payout-protected account is explained and excluded from starting',
    (tester) async {
      SharedPreferences.setMockInitialValues({});
      final storage = await LocalAppStorage.create();
      final controller = PropFirmPlannerController(
        repository: PropFirmPortfolioRepository(
          storage: storage,
          ownerId: 'sequence-payout-widget',
        ),
        now: () => DateTime(2026, 7, 21, 9),
      );
      await controller.initialize();
      await controller.createStarterPortfolio(virtualRiskBudget: 3000);
      final protected = controller.portfolio!.accounts.last;
      await controller.saveAccount(
        protected.copyWith(status: AccountStatus.payoutProtection),
      );
      controller.dispose();

      await tester.binding.setSurfaceSize(const Size(1280, 1050));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await _pumpPlanner(
        tester,
        storage,
        'sequence-payout-widget',
        size: const Size(1280, 1050),
      );
      await tester.drag(find.byType(ListView).first, const Offset(0, -850));
      await tester.pumpAndSettle();
      expect(find.text('Payout protected'), findsOneWidget);
      expect(find.byKey(Key('set-starting-${protected.id}')), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('mobile exposes exactly three simplified destinations', (
    tester,
  ) async {
    final storage = await _starterStorage('sequence-mobile-widget');
    await tester.binding.setSurfaceSize(const Size(390, 844));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await _pumpPlanner(
      tester,
      storage,
      'sequence-mobile-widget',
      size: const Size(390, 844),
    );

    final navigation = tester.widget<NavigationBar>(find.byType(NavigationBar));
    expect(navigation.destinations, hasLength(3));
    navigation.onDestinationSelected?.call(2);
    await tester.pumpAndSettle();
    expect(find.text('Tutorial, Guidelines & FAQ'), findsOneWidget);
    expect(find.byKey(const Key('simplified-tutorial')), findsOneWidget);
    expect(find.byKey(const Key('health-score-donut')), findsWidgets);
    expect(find.byKey(const Key('recovery-journey')), findsOneWidget);
    expect(find.byKey(const Key('professional-faq-list')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('professional FAQ visuals render in Chinese on Windows width', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(1280, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
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
        home: const Scaffold(body: SimplifiedTutorialPage()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('了解 0–100 健康分数'), findsOneWidget);
    expect(find.byKey(const Key('health-score-donut')), findsWidgets);
    expect(find.byKey(const Key('recovery-journey')), findsOneWidget);
    expect(find.byKey(const Key('professional-faq-list')), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Future<LocalAppStorage> _starterStorage(String ownerId) async {
  SharedPreferences.setMockInitialValues({});
  final storage = await LocalAppStorage.create();
  final controller = PropFirmPlannerController(
    repository: PropFirmPortfolioRepository(storage: storage, ownerId: ownerId),
    now: () => DateTime(2026, 7, 21, 9),
  );
  await controller.initialize();
  await controller.createStarterPortfolio(virtualRiskBudget: 3000);
  controller.dispose();
  return storage;
}

Future<void> _pumpPlanner(
  WidgetTester tester,
  LocalAppStorage storage,
  String ownerId, {
  required Size size,
}) async {
  await tester.pumpWidget(
    MaterialApp(
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: MediaQuery(
        data: MediaQueryData(size: size),
        child: PropFirmPlannerScreen(
          storage: storage,
          ownerId: ownerId,
          showBackButton: false,
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}
