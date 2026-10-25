import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:hc_trader/features/tools/average_price/presentation/average_price_screen.dart';
import 'package:hc_trader/features/tools/average_price/presentation/average_price_widgets.dart';
import 'package:hc_trader/features/tools/tools_screen.dart';
import 'package:hc_trader/models/access_status.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/repositories/trading_repository.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('feature opens from Trading Toolkit at its named route', (
    tester,
  ) async {
    final harness = await _pumpTools(tester);
    addTearDown(harness.dispose);

    await tester.enterText(find.byType(SearchBar), 'average price');
    await tester.pumpAndSettle();
    final tile = find.byKey(const ValueKey('tool-average_price'));
    expect(tile, findsOneWidget);
    await tester.ensureVisible(tile);
    await tester.tap(tile);
    await tester.pumpAndSettle();

    expect(find.byType(AveragePriceScreen), findsOneWidget);
    expect(
      ModalRoute.of(
        tester.element(find.byType(AveragePriceScreen)),
      )?.settings.name,
      AveragePriceScreen.routeName,
    );
  });

  testWidgets('orders, direction, planner and what-if flow update live', (
    tester,
  ) async {
    final harness = await _pumpFeature(tester, size: const Size(430, 1050));
    addTearDown(harness.dispose);

    expect(find.byType(PositionOrderCard), findsNWidgets(2));
    final priceFields = _fieldWithLabel('Entry price');
    final quantityFields = _fieldWithLabel('Quantity / lots');
    await tester.enterText(priceFields.at(0), '4000');
    await tester.enterText(quantityFields.at(0), '1');
    await tester.enterText(priceFields.at(1), '4010');
    await tester.enterText(quantityFields.at(1), '1');
    await tester.pump();

    expect(find.text('4005.00'), findsWidgets);

    tester.testTextInput.hide();
    await _scrollTo(tester, find.byKey(const ValueKey('direction-short')));
    await tester.tap(find.byKey(const ValueKey('direction-short')));
    await tester.pump();
    expect(
      tester
          .widget<ChoiceChip>(find.byKey(const ValueKey('simple-mode')))
          .selected,
      isTrue,
    );

    await tester.tap(find.byKey(const ValueKey('advanced-mode')));
    await tester.pump();
    expect(
      tester
          .widget<ChoiceChip>(find.byKey(const ValueKey('advanced-mode')))
          .selected,
      isTrue,
    );

    await _scrollTo(tester, find.byKey(const ValueKey('stop-loss-field')));
    await tester.enterText(
      find.byKey(const ValueKey('stop-loss-field')),
      '4020',
    );
    await tester.enterText(_fieldWithLabel('Target price').first, '3980');
    await tester.pump();

    await _scrollTo(tester, find.byKey(const ValueKey('simulation-price')));
    await tester.enterText(
      find.byKey(const ValueKey('simulation-price')),
      '3990',
    );
    await tester.enterText(
      find.byKey(const ValueKey('simulation-quantity')),
      '2',
    );
    await tester.pump();
    expect(find.text('Projected Average'), findsOneWidget);

    final beforeApply = find.byType(PositionOrderCard).evaluate().length;
    expect(beforeApply, 2);
    await _scrollTo(tester, find.byKey(const ValueKey('apply-simulation')));
    await tester.tap(find.byKey(const ValueKey('apply-simulation')));
    await tester.pump();
    expect(find.byType(PositionOrderCard), findsNWidgets(3));
  });

  testWidgets('multiple TP allocation warning, reset, save and load work', (
    tester,
  ) async {
    final harness = await _pumpFeature(tester, size: const Size(500, 1050));
    addTearDown(harness.dispose);

    final priceFields = _fieldWithLabel('Entry price');
    final quantityFields = _fieldWithLabel('Quantity / lots');
    await tester.enterText(priceFields.first, '100');
    await tester.enterText(quantityFields.first, '1');
    await tester.enterText(_fieldWithLabel('Target price').first, '110');
    await tester.enterText(_fieldWithLabel('Close allocation %').first, '70');
    await _scrollTo(tester, find.byKey(const ValueKey('add-take-profit')));
    await tester.tap(find.byKey(const ValueKey('add-take-profit')));
    await tester.pump();
    await tester.enterText(_fieldWithLabel('Target price').last, '120');
    await tester.enterText(_fieldWithLabel('Close allocation %').last, '50');
    await tester.pump();

    expect(
      find.text('Take-profit close allocation exceeds 100%.'),
      findsOneWidget,
    );

    await tester.tap(find.byKey(const ValueKey('save-average-setup')));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const ValueKey('setup-name-field')),
      'Test setup',
    );
    await tester.tap(find.byKey(const ValueKey('confirm-save-setup')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
    expect(find.text('Calculation saved.'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('average-price-history')));
    await tester.pumpAndSettle();
    expect(find.text('Test setup'), findsOneWidget);
    await tester.tap(find.text('Test setup'));
    await tester.pumpAndSettle();
    expect(find.text('100.00'), findsWidgets);

    await _scrollTo(tester, find.byKey(const ValueKey('reset-average-price')));
    await tester.tap(find.byKey(const ValueKey('reset-average-price')));
    await tester.pumpAndSettle();
    expect(find.text('Reset calculation?'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('confirm-reset-average-price')));
    await tester.pumpAndSettle();
    expect(find.text('—'), findsWidgets);
  });

  for (final testCase in const [
    ('small mobile light', Size(320, 900), Locale('en'), ThemeMode.light),
    ('mobile Chinese dark', Size(412, 900), Locale('zh'), ThemeMode.dark),
    ('tablet', Size(800, 950), Locale('en'), ThemeMode.light),
    ('Windows desktop', Size(1440, 960), Locale('zh'), ThemeMode.dark),
  ]) {
    testWidgets('${testCase.$1} layout has no overflow', (tester) async {
      final harness = await _pumpFeature(
        tester,
        size: testCase.$2,
        locale: testCase.$3,
        themeMode: testCase.$4,
        textScaler: const TextScaler.linear(1.15),
      );
      addTearDown(harness.dispose);

      expect(find.byType(AveragePriceHeroCard), findsOneWidget);
      if (testCase.$3.languageCode == 'zh') {
        expect(find.text('平均价格与保本计算器'), findsWidgets);
      }
      expect(tester.takeException(), isNull);
    });
  }
}

Finder _fieldWithLabel(String label) =>
    find.widgetWithText(TextFormField, label);

Future<void> _scrollTo(WidgetTester tester, Finder target) async {
  await tester.scrollUntilVisible(
    target,
    500,
    scrollable: find.byType(Scrollable).first,
  );
  await tester.pumpAndSettle();
}

const _user = AppUser(
  uid: 'average-test-user',
  email: 'average@example.com',
  accessStatus: AccessStatus.approved,
  mainMt5AccountKey: 'main-account',
);

class _Harness {
  const _Harness(this.tester);

  final WidgetTester tester;

  Future<void> dispose() async {
    FocusManager.instance.primaryFocus?.unfocus();
    await tester.pump();
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump();
    await tester.binding.setSurfaceSize(null);
  }
}

Future<_Harness> _pumpFeature(
  WidgetTester tester, {
  Size size = const Size(900, 1000),
  Locale locale = const Locale('en'),
  ThemeMode themeMode = ThemeMode.light,
  TextScaler textScaler = TextScaler.noScaling,
}) async {
  SharedPreferences.setMockInitialValues({});
  final storage = await LocalAppStorage.create();
  await tester.binding.setSurfaceSize(size);
  await tester.pumpWidget(
    MaterialApp(
      locale: locale,
      themeMode: themeMode,
      theme: ThemeData(useMaterial3: true, brightness: Brightness.light),
      darkTheme: ThemeData(useMaterial3: true, brightness: Brightness.dark),
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
      home: AveragePriceScreen(user: _user, storage: storage),
    ),
  );
  await tester.pumpAndSettle();
  return _Harness(tester);
}

Future<_Harness> _pumpTools(WidgetTester tester) async {
  SharedPreferences.setMockInitialValues({});
  final storage = await LocalAppStorage.create();
  final repository = TradingRepository(
    firebaseEnabled: false,
    storage: storage,
  );
  await tester.binding.setSurfaceSize(const Size(900, 1000));
  await tester.pumpWidget(
    MaterialApp(
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: ToolsScreen(user: _user, repository: repository, storage: storage),
    ),
  );
  await tester.pumpAndSettle();
  return _Harness(tester);
}
