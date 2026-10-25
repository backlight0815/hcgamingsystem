import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:hc_trader/features/tools/average_price/application/average_price_controller.dart';
import 'package:hc_trader/features/tools/average_price/domain/average_price_models.dart';
import 'package:hc_trader/models/mt5_import_models.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('controller starts with two rows and recalculates reactively', () async {
    SharedPreferences.setMockInitialValues({});
    final storage = await LocalAppStorage.create();
    final controller = AveragePriceController(
      userId: 'user-1',
      storage: storage,
    );
    addTearDown(controller.dispose);

    expect(controller.orders, hasLength(2));
    controller.updateOrder(
      controller.orders[0].id,
      price: '4000',
      quantity: '1',
    );
    controller.updateOrder(
      controller.orders[1].id,
      price: '4010',
      quantity: '1',
    );

    expect(controller.result.weightedAverageEntry, Mt5Decimal.parse('4005'));
    controller.setDirection(PositionDirection.short);
    expect(controller.direction, PositionDirection.short);
    expect(controller.result.weightedAverageEntry, Mt5Decimal.parse('4005'));
  });

  test(
    'what-if preview does not mutate orders until explicitly applied',
    () async {
      SharedPreferences.setMockInitialValues({});
      final storage = await LocalAppStorage.create();
      final controller = AveragePriceController(
        userId: 'user-1',
        storage: storage,
      );
      addTearDown(controller.dispose);
      controller.updateOrder(
        controller.orders.first.id,
        price: '100',
        quantity: '1',
      );
      controller.updateOrder(
        controller.orders.last.id,
        price: '110',
        quantity: '1',
      );

      final originalCount = controller.orders.length;
      controller.updateSimulation(price: '90', quantity: '2', fee: '1');

      expect(controller.orders, hasLength(originalCount));
      expect(controller.simulationResult, isNotNull);
      expect(
        controller.simulationResult!.projected.weightedAverageEntry,
        Mt5Decimal.parse('97.5'),
      );

      controller.applySimulation();
      expect(controller.orders, hasLength(originalCount + 1));
      expect(controller.result.weightedAverageEntry, Mt5Decimal.parse('97.5'));
      expect(controller.simulationResult, isNull);
    },
  );

  test(
    'saved history is user-scoped, searchable, loadable and duplicable',
    () async {
      SharedPreferences.setMockInitialValues({});
      final storage = await LocalAppStorage.create();
      final nowValues = <DateTime>[
        DateTime(2026, 7, 20, 10),
        DateTime(2026, 7, 20, 11),
        DateTime(2026, 7, 20, 12),
      ];
      var nowIndex = 0;
      final controller = AveragePriceController(
        userId: 'user-1',
        storage: storage,
        now: () => nowValues[nowIndex++],
      );
      addTearDown(controller.dispose);
      controller.setSymbol('XAUUSD');
      controller.updateOrder(
        controller.orders[0].id,
        price: '2400',
        quantity: '1',
      );
      controller.updateOrder(
        controller.orders[1].id,
        price: '2410',
        quantity: '1',
      );

      final saved = await controller.saveSetup('London gold');
      expect(controller.history, hasLength(1));
      expect(controller.searchHistory('xau'), hasLength(1));
      expect(controller.searchHistory('London'), hasLength(1));

      await controller.duplicateSetup(saved.id, 'copy');
      expect(controller.history, hasLength(2));

      controller.reset();
      expect(controller.result.weightedAverageEntry, isNull);
      controller.loadSetup(
        controller.history.where((item) => item.id == saved.id).single,
      );
      expect(controller.symbol, 'XAUUSD');
      expect(controller.result.weightedAverageEntry, Mt5Decimal.parse('2405'));

      final duplicate = controller.history
          .where((item) => item.id != saved.id)
          .single;
      await controller.deleteSetup(duplicate.id);
      expect(controller.history, hasLength(1));

      final otherUser = AveragePriceController(
        userId: 'user-2',
        storage: storage,
      );
      addTearDown(otherUser.dispose);
      expect(otherUser.history, isEmpty);
    },
  );
}
