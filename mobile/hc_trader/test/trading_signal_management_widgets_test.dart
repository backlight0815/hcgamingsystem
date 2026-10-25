import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/features/admin/trading_signal_management_screen.dart';
import 'package:hc_trader/features/admin/admin_management_screen.dart';
import 'package:hc_trader/features/hub/trade_call_detail_screen.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/models/trading_signal_item.dart';
import 'package:hc_trader/models/trading_signal_lifecycle.dart';
import 'package:hc_trader/repositories/trading_repository.dart';

TradingSignalItem managedSignal({
  String lifecycle = 'active',
  Set<String> operatorIds = const {},
}) => TradingSignalItem(
  id: 'signal-1',
  tradingPair: 'XAUUSD',
  signalCode: 'SIG-001',
  immediateAction: 'BUY',
  orderType: 'buy_limit',
  entryPrice: '2300',
  originalEntryPrice: '2300',
  stopLoss: '2290',
  originalStopLoss: '2290',
  currentStopLoss: '2295',
  targets: const ['2310', '2320'],
  targetsHit: const {1},
  percentageRemaining: 50,
  riskLevel: 'Medium',
  lifecycleStatus: lifecycle,
  providerId: 'provider-a',
  operatorIds: operatorIds,
  version: 3,
  publishedAt: DateTime(2026, 7, 15, 10),
);

Widget app(Widget child, {Locale locale = const Locale('en')}) => MaterialApp(
  locale: locale,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  supportedLocales: AppLocalizations.supportedLocales,
  home: Scaffold(body: child),
);

class _SignalScreenRepository extends TradingRepository {
  _SignalScreenRepository(
    this.signalStream, {
    this.updateStream = const Stream.empty(),
  }) : super(firebaseEnabled: false);

  final Stream<TradingSignalItem?> signalStream;
  final Stream<List<TradingSignalUpdate>> updateStream;

  @override
  Stream<TradingSignalItem?> tradingSignalStream(String signalId) =>
      signalStream;

  @override
  Stream<List<TradingSignalUpdate>> tradingSignalUpdatesStream(
    String signalId,
  ) => updateStream;
}

void main() {
  test('legacy and current signal ownership fields remain operable', () {
    final signal = TradingSignalItem.fromJson(const {
      'id': 'signal-ownership',
      'trading_pair': 'EURUSD',
      'provider_id': 'provider-a',
      'assignedProviderIds': ['provider-b'],
      'created_by_id': 'provider-c',
    });

    expect(signal.isOperableBy('provider-a'), isTrue);
    expect(signal.isOperableBy('provider-b'), isTrue);
    expect(signal.isOperableBy('provider-c'), isTrue);
    expect(signal.isOperableBy('provider-d'), isFalse);
  });

  testWidgets('backend workspace header is compact and searchable', (
    tester,
  ) async {
    final controller = TextEditingController();
    addTearDown(controller.dispose);
    var query = '';
    await tester.pumpWidget(
      app(
        AdminWorkspaceHeader(
          user: const AppUser(
            uid: 'admin-a',
            email: 'admin@example.test',
            displayName: 'Admin A',
            roleId: 1,
            roleIds: {1},
            isAdmin: true,
          ),
          searchController: controller,
          onSearchChanged: (value) => query = value,
          onRefresh: () async {},
          onCreateSignal: () {},
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('admin-workspace-header')), findsOneWidget);
    expect(find.text('Manage Backend'), findsOneWidget);
    expect(find.textContaining('Admin A'), findsOneWidget);
    await tester.enterText(
      find.byKey(const Key('admin-workspace-search')),
      'signal',
    );
    expect(query, 'signal');
  });

  testWidgets('signal operations panel renders real metric values', (
    tester,
  ) async {
    await tester.pumpWidget(
      app(
        SignalOperationsPanel(
          metrics: Stream.value(
            const SignalOperationsMetrics(
              total: 12,
              active: 4,
              pendingEntry: 2,
              recentTargetHits: 3,
              nearExpiry: 1,
              stale: 1,
              completedToday: 5,
              cancelled: 2,
            ),
          ),
          onOpen: () {},
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('signal-operations-panel')), findsOneWidget);
    expect(find.text('Active'), findsOneWidget);
    expect(find.text('4'), findsOneWidget);
    expect(find.text('Completed today'), findsOneWidget);
    expect(find.text('5'), findsOneWidget);
  });

  testWidgets('backend module search filters the responsive workspace', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1400, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final repository = TradingRepository(firebaseEnabled: false);
    await tester.pumpWidget(
      app(
        AdminManagementScreen(
          user: const AppUser(
            uid: 'admin-a',
            email: 'admin@example.test',
            roleId: 1,
            roleIds: {1},
            isAdmin: true,
          ),
          repository: repository,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('admin-modules-workspace')), findsOneWidget);
    expect(
      find.byKey(const Key('admin-module-trading_signals')),
      findsOneWidget,
    );
    expect(find.byKey(const Key('admin-module-trading_news')), findsOneWidget);

    await tester.tap(find.widgetWithText(ListTile, 'Learning'));
    await tester.pump();
    expect(find.byKey(const Key('admin-module-coursework')), findsOneWidget);
    expect(find.byKey(const Key('admin-module-trading_signals')), findsNothing);

    await tester.tap(find.widgetWithText(ListTile, 'All'));
    await tester.pump();

    await tester.enterText(
      find.byKey(const Key('admin-workspace-search')),
      'signal',
    );
    await tester.pump();
    expect(
      find.byKey(const Key('admin-module-trading_signals')),
      findsOneWidget,
    );
    expect(find.byKey(const Key('admin-module-trading_news')), findsNothing);
  });

  testWidgets('signal header renders in Simplified Chinese locale', (
    tester,
  ) async {
    await tester.pumpWidget(
      app(
        SignalOperationsHeader(signal: managedSignal()),
        locale: const Locale('zh'),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('signal-operations-header')), findsOneWidget);
    expect(find.text('\u5f53\u524d\u6b62\u635f'), findsOneWidget);
    expect(find.text('\u8fdb\u884c\u4e2d'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('signal provider sees signal modules but not governance', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1200, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      app(
        AdminManagementScreen(
          user: const AppUser(
            uid: 'provider-a',
            email: 'provider@example.test',
            roleId: 201,
            roleIds: {201},
          ),
          repository: TradingRepository(firebaseEnabled: false),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(
      find.byKey(const Key('admin-module-trading_signals')),
      findsOneWidget,
    );
    expect(find.text('Governance'), findsNothing);
    expect(find.byKey(const Key('admin-module-trading_news')), findsNothing);
  });

  testWidgets('signal screen shows loading and friendly stream errors', (
    tester,
  ) async {
    const user = AppUser(
      uid: 'provider-a',
      email: 'provider@example.test',
      roleId: 201,
      roleIds: {201},
    );
    await tester.pumpWidget(
      app(
        TradingSignalManagementScreen(
          user: user,
          repository: _SignalScreenRepository(const Stream.empty()),
          signalId: 'signal-1',
        ),
      ),
    );
    await tester.pump();
    expect(find.byType(CircularProgressIndicator), findsOneWidget);

    await tester.pumpWidget(
      app(
        TradingSignalManagementScreen(
          user: user,
          repository: _SignalScreenRepository(
            Stream.error(StateError('sensitive backend detail')),
          ),
          signalId: 'signal-1',
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Unable to load this signal'), findsOneWidget);
    expect(find.textContaining('sensitive backend detail'), findsNothing);
  });

  testWidgets('provider cannot manage another provider signal', (tester) async {
    await tester.pumpWidget(
      app(
        TradingSignalManagementScreen(
          user: const AppUser(
            uid: 'provider-b',
            email: 'provider-b@example.test',
            roleId: 201,
            roleIds: {201},
          ),
          repository: _SignalScreenRepository(Stream.value(managedSignal())),
          signalId: 'signal-1',
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('This signal is read-only'), findsOneWidget);
    expect(find.byKey(const Key('signal-quick-actions')), findsNothing);
  });

  testWidgets('assigned provider can manage a signal created by another', (
    tester,
  ) async {
    await tester.pumpWidget(
      app(
        TradingSignalManagementScreen(
          user: const AppUser(
            uid: 'provider-b',
            email: 'provider-b@example.test',
            roleId: 201,
            roleIds: {201},
          ),
          repository: _SignalScreenRepository(
            Stream.value(managedSignal(operatorIds: const {'provider-b'})),
          ),
          signalId: 'signal-1',
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('signal-quick-actions')), findsOneWidget);
    expect(find.text('This signal is read-only'), findsNothing);
  });

  testWidgets('public signal details render the localized update timeline', (
    tester,
  ) async {
    final timelineUpdate = TradingSignalUpdate(
      id: 'target-v4',
      updateType: SignalUpdateType.targetHit,
      lifecycleFrom: SignalLifecycleStatus.active,
      lifecycleTo: SignalLifecycleStatus.partiallyClosed,
      titleI18n: const {'zh': '\u7b2c\u4e00\u76ee\u6807\u5df2\u8fbe\u6210'},
      messageI18n: const {'en': 'TP1 reached and risk reduced.'},
      previousValues: const {'targets_hit': <int>[]},
      newValues: const {
        'targets_hit': <int>[1],
      },
      createdBy: 'provider-a',
      createdByName: 'Provider A',
      createdByRole: 'Signal Provider',
      reason: '',
      attachmentUrls: const [],
      notificationRequested: true,
      notificationSent: true,
      isCorrection: false,
      correctedUpdateId: '',
      createdAt: DateTime(2026, 7, 15, 12),
    );
    await tester.pumpWidget(
      app(
        TradeCallDetailScreen(
          signal: managedSignal(),
          repository: _SignalScreenRepository(
            const Stream.empty(),
            updateStream: Stream.value([timelineUpdate]),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(
      find.byKey(const ValueKey('public-signal-update-timeline')),
      findsOneWidget,
    );
    expect(find.text('TP1 reached and risk reduced.'), findsOneWidget);
    expect(find.textContaining('Active'), findsWidgets);
  });

  testWidgets('operations header shows current and original risk state', (
    tester,
  ) async {
    await tester.pumpWidget(
      app(SignalOperationsHeader(signal: managedSignal())),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('signal-operations-header')), findsOneWidget);
    expect(find.text('XAUUSD'), findsOneWidget);
    expect(find.text('2290'), findsOneWidget);
    expect(find.text('2295'), findsOneWidget);
    expect(find.text('1/2'), findsOneWidget);
    expect(find.text('50%'), findsOneWidget);
    expect(find.text('v3'), findsOneWidget);
  });

  testWidgets('active signal exposes lifecycle-aware actions', (tester) async {
    SignalUpdateType? selected;
    await tester.pumpWidget(
      app(
        SignalQuickActions(
          signal: managedSignal(),
          superAdmin: false,
          onSelected: (value) => selected = value,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(
      find.byKey(const Key('signal-action-update_stop_loss')),
      findsOneWidget,
    );
    expect(find.byKey(const Key('signal-action-target_hit')), findsOneWidget);
    expect(find.byKey(const Key('signal-action-correction')), findsNothing);

    await tester.tap(find.byKey(const Key('signal-action-target_hit')));
    expect(selected, SignalUpdateType.targetHit);
  });

  testWidgets('terminal signal replaces trading actions with archive', (
    tester,
  ) async {
    await tester.pumpWidget(
      app(
        SignalQuickActions(
          signal: managedSignal(lifecycle: 'completed'),
          superAdmin: true,
          onSelected: (_) {},
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('signal-action-archived')), findsOneWidget);
    expect(
      find.byKey(const Key('signal-action-update_stop_loss')),
      findsNothing,
    );
  });

  testWidgets('stop-loss action renders its structured dynamic fields', (
    tester,
  ) async {
    await tester.pumpWidget(
      app(
        SignalUpdateDialog(
          user: const AppUser(
            uid: 'provider-a',
            email: 'provider@example.test',
            roleId: 201,
            roleIds: {201},
          ),
          repository: TradingRepository(firebaseEnabled: false),
          signal: managedSignal(),
          updateType: SignalUpdateType.updateStopLoss,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Previous value'), findsOneWidget);
    expect(find.text('New stop loss'), findsOneWidget);
    expect(find.text('Reason (required)'), findsOneWidget);
    expect(find.text('Effective date and time'), findsOneWidget);
    expect(find.text('Notify signal followers'), findsOneWidget);
    expect(find.byKey(const Key('submit-signal-update')), findsOneWidget);
  });

  testWidgets('timeline entry renders audit and notification details', (
    tester,
  ) async {
    await tester.pumpWidget(
      app(
        SignalTimelineEntry(
          update: TradingSignalUpdate(
            id: 'update-v4',
            updateType: SignalUpdateType.updateStopLoss,
            lifecycleFrom: SignalLifecycleStatus.active,
            lifecycleTo: SignalLifecycleStatus.trailing,
            titleI18n: const {},
            messageI18n: const {'en': 'SL now protects profit.'},
            previousValues: const {'current_stop_loss': '2290'},
            newValues: const {'current_stop_loss': '2305'},
            createdBy: 'provider-a',
            createdByName: 'Provider A',
            createdByRole: 'Signal Provider',
            reason: 'Protect profit',
            attachmentUrls: const ['https://example.test/chart.png'],
            notificationRequested: true,
            notificationSent: true,
            isCorrection: false,
            correctedUpdateId: '',
            effectiveAt: DateTime(2026, 7, 15, 12),
            createdAt: DateTime(2026, 7, 15, 12, 1),
          ),
          language: 'en',
          last: true,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('SL now protects profit.'), findsOneWidget);
    expect(find.textContaining('ID update-v4'), findsOneWidget);
    expect(find.text('Notification sent'), findsOneWidget);
    expect(find.text('Chart attached'), findsOneWidget);
    expect(find.textContaining('2290 → 2305'), findsOneWidget);
  });

  testWidgets('signal management empty state explains unavailable data', (
    tester,
  ) async {
    await tester.pumpWidget(
      app(
        const SignalManagementEmptyState(
          icon: Icons.cloud_off_outlined,
          title: 'Unable to load this signal',
          message: 'Check your connection and try again.',
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Unable to load this signal'), findsOneWidget);
    expect(find.text('Check your connection and try again.'), findsOneWidget);
    expect(find.byIcon(Icons.cloud_off_outlined), findsOneWidget);
  });
}
