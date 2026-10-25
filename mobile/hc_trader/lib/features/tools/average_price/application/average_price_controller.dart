import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../../core/storage/local_app_storage.dart';
import '../../../../models/mt5_import_models.dart';
import '../data/average_price_history_store.dart';
import '../domain/average_price_calculator.dart';
import '../domain/average_price_models.dart';
import 'average_price_form_models.dart';

class AveragePriceController extends ChangeNotifier {
  AveragePriceController({
    required this.userId,
    required LocalAppStorage storage,
    DateTime Function()? now,
  }) : _historyStore = AveragePriceHistoryStore(storage, now: now) {
    _restoreDraft();
  }

  final String userId;
  final AveragePriceHistoryStore _historyStore;
  Timer? _draftTimer;
  var _idCounter = 0;

  PositionDirection direction = PositionDirection.long;
  AveragePriceMode mode = AveragePriceMode.simple;
  List<PositionOrderDraft> orders = const [];
  List<TakeProfitDraft> takeProfits = const [];
  String symbol = '';
  AssetCategory assetCategory = AssetCategory.custom;
  int pricePrecision = 2;
  QuantityType quantityType = QuantityType.units;
  String contractSize = '';
  String tickSize = '';
  String tickValue = '';
  String pipSize = '';
  String accountCurrency = 'USD';
  String currentMarketPrice = '';
  String entryCommissionPercent = '0';
  String exitCommissionPercent = '0';
  String spread = '0';
  String slippage = '0';
  String swapCost = '0';
  String otherCosts = '0';
  String bufferValue = '0';
  SafetyBufferType bufferType = SafetyBufferType.points;
  String stopLoss = '';
  String targetRiskReward = '2';
  String notes = '';
  String? selectedSetupId;
  var formEpoch = 0;

  late AveragePriceCalculationResult result;
  WhatIfOrderDraft simulationDraft = const WhatIfOrderDraft();
  WhatIfCalculationResult? simulationResult;
  List<SavedAveragePriceSetup> history = const [];

  bool get isAdvanced => mode == AveragePriceMode.advanced;

  bool get hasMeaningfulData =>
      orders.any(
        (order) =>
            order.price.trim().isNotEmpty ||
            order.quantity.trim().isNotEmpty ||
            order.fee.trim().isNotEmpty ||
            order.note.trim().isNotEmpty,
      ) ||
      takeProfits.any((target) => target.price.trim().isNotEmpty) ||
      symbol.trim().isNotEmpty ||
      stopLoss.trim().isNotEmpty ||
      currentMarketPrice.trim().isNotEmpty ||
      notes.trim().isNotEmpty;

  AveragePriceSetupSnapshot get snapshot => AveragePriceSetupSnapshot(
    direction: direction,
    mode: mode,
    orders: List.unmodifiable(orders),
    takeProfits: List.unmodifiable(takeProfits),
    symbol: symbol,
    assetCategory: assetCategory,
    pricePrecision: pricePrecision,
    quantityType: quantityType,
    contractSize: contractSize,
    tickSize: tickSize,
    tickValue: tickValue,
    pipSize: pipSize,
    accountCurrency: accountCurrency,
    currentMarketPrice: currentMarketPrice,
    entryCommissionPercent: entryCommissionPercent,
    exitCommissionPercent: exitCommissionPercent,
    spread: spread,
    slippage: slippage,
    swapCost: swapCost,
    otherCosts: otherCosts,
    bufferValue: bufferValue,
    bufferType: bufferType,
    stopLoss: stopLoss,
    targetRiskReward: targetRiskReward,
    notes: notes,
  );

  void _restoreDraft() {
    history = _historyStore.load(userId);
    final saved = _historyStore.loadDraft(userId);
    if (saved == null) {
      _applySnapshot(_blankSnapshot(), external: false);
    } else {
      _applySnapshot(saved, external: false);
    }
    _recalculate(notify: false, autosave: false);
  }

  void setDirection(PositionDirection value) {
    if (direction == value) return;
    direction = value;
    _commit();
  }

  void setMode(AveragePriceMode value) {
    if (mode == value) return;
    mode = value;
    _commit();
  }

  void updateOrder(
    String id, {
    String? price,
    String? quantity,
    String? fee,
    String? note,
  }) {
    orders = [
      for (final order in orders)
        if (order.id == id)
          order.copyWith(price: price, quantity: quantity, fee: fee, note: note)
        else
          order,
    ];
    _commit();
  }

  void addOrder() {
    orders = [...orders, PositionOrderDraft(id: _newId('order'))];
    _commit();
  }

  void duplicateOrder(String id) {
    final index = orders.indexWhere((order) => order.id == id);
    if (index < 0) return;
    final copy = orders[index].copyWith(id: _newId('order'));
    orders = [...orders]..insert(index + 1, copy);
    _commit();
  }

  void deleteOrder(String id) {
    orders = orders.where((order) => order.id != id).toList(growable: false);
    _commit();
  }

  void reorderOrder(int oldIndex, int newIndex) {
    if (oldIndex < 0 || oldIndex >= orders.length) return;
    final next = [...orders];
    if (newIndex > oldIndex) newIndex--;
    final item = next.removeAt(oldIndex);
    next.insert(newIndex.clamp(0, next.length), item);
    orders = next;
    _commit();
  }

  void updateTakeProfit(String id, {String? price, String? percentage}) {
    takeProfits = [
      for (final target in takeProfits)
        if (target.id == id)
          target.copyWith(price: price, percentage: percentage)
        else
          target,
    ];
    _commit();
  }

  void addTakeProfit() {
    final remaining = result.remainingPercentage;
    takeProfits = [
      ...takeProfits,
      TakeProfitDraft(
        id: _newId('tp'),
        percentage: remaining.isPositive ? remaining.toString() : '',
      ),
    ];
    _commit();
  }

  void deleteTakeProfit(String id) {
    takeProfits = takeProfits
        .where((target) => target.id != id)
        .toList(growable: false);
    _commit();
  }

  void setSymbol(String value) => _setString(() => symbol = value);
  void setContractSize(String value) => _setString(() => contractSize = value);
  void setTickSize(String value) => _setString(() => tickSize = value);
  void setTickValue(String value) => _setString(() => tickValue = value);
  void setPipSize(String value) => _setString(() => pipSize = value);
  void setAccountCurrency(String value) =>
      _setString(() => accountCurrency = value);
  void setCurrentMarketPrice(String value) =>
      _setString(() => currentMarketPrice = value);
  void setEntryCommission(String value) =>
      _setString(() => entryCommissionPercent = value);
  void setExitCommission(String value) =>
      _setString(() => exitCommissionPercent = value);
  void setSpread(String value) => _setString(() => spread = value);
  void setSlippage(String value) => _setString(() => slippage = value);
  void setSwapCost(String value) => _setString(() => swapCost = value);
  void setOtherCosts(String value) => _setString(() => otherCosts = value);
  void setBufferValue(String value) => _setString(() => bufferValue = value);
  void setStopLoss(String value) => _setString(() => stopLoss = value);
  void setTargetRiskReward(String value) =>
      _setString(() => targetRiskReward = value);
  void setNotes(String value) => _setString(() => notes = value);

  void setAssetCategory(AssetCategory value) {
    assetCategory = value;
    _commit();
  }

  void setQuantityType(QuantityType value) {
    quantityType = value;
    _commit();
  }

  void setPricePrecision(int value) {
    pricePrecision = value.clamp(0, 8);
    _commit();
  }

  void setBufferType(SafetyBufferType value) {
    bufferType = value;
    _commit();
  }

  void updateSimulation({String? price, String? quantity, String? fee}) {
    simulationDraft = simulationDraft.copyWith(
      price: price,
      quantity: quantity,
      fee: fee,
    );
    _calculateSimulation();
    notifyListeners();
  }

  void resetSimulation() {
    simulationDraft = const WhatIfOrderDraft();
    simulationResult = null;
    formEpoch++;
    notifyListeners();
  }

  void applySimulation() {
    if (!simulationDraft.isValid) return;
    orders = [
      ...orders,
      PositionOrderDraft(
        id: _newId('order'),
        price: simulationDraft.price,
        quantity: simulationDraft.quantity,
        fee: simulationDraft.fee,
        note: 'What-if order',
      ),
    ];
    simulationDraft = const WhatIfOrderDraft();
    simulationResult = null;
    formEpoch++;
    _commit();
  }

  Future<SavedAveragePriceSetup> saveSetup(String setupName) async {
    final saved = await _historyStore.save(
      userId: userId,
      name: setupName,
      snapshot: snapshot,
      existingId: selectedSetupId,
    );
    selectedSetupId = saved.id;
    history = _historyStore.load(userId);
    await _historyStore.saveDraft(userId, snapshot);
    notifyListeners();
    return saved;
  }

  Future<SavedAveragePriceSetup?> duplicateSetup(
    String setupId,
    String suffix,
  ) async {
    final saved = await _historyStore.duplicate(userId, setupId, suffix);
    history = _historyStore.load(userId);
    notifyListeners();
    return saved;
  }

  Future<void> deleteSetup(String setupId) async {
    await _historyStore.delete(userId, setupId);
    if (selectedSetupId == setupId) selectedSetupId = null;
    history = _historyStore.load(userId);
    notifyListeners();
  }

  void loadSetup(SavedAveragePriceSetup setup) {
    selectedSetupId = setup.id;
    _applySnapshot(setup.snapshot);
    _recalculate();
  }

  List<SavedAveragePriceSetup> searchHistory(String query) =>
      _historyStore.search(userId, query);

  void reset() {
    selectedSetupId = null;
    simulationDraft = const WhatIfOrderDraft();
    simulationResult = null;
    _applySnapshot(_blankSnapshot());
    _recalculate();
  }

  void _setString(VoidCallback update) {
    update();
    _commit();
  }

  void _commit() {
    selectedSetupId = null;
    _recalculate();
  }

  void _recalculate({bool notify = true, bool autosave = true}) {
    result = AveragePricePositionEngine(snapshot.toRequest()).calculate();
    _calculateSimulation();
    if (autosave) _scheduleDraftSave();
    if (notify) notifyListeners();
  }

  void _calculateSimulation() {
    if (!simulationDraft.isValid || !result.canCalculate) {
      simulationResult = null;
      return;
    }
    final proposedOrder = PositionOrderDraft(
      id: 'simulation',
      price: simulationDraft.price,
      quantity: simulationDraft.quantity,
      fee: simulationDraft.fee,
    );
    final projectedSnapshot = AveragePriceSetupSnapshot(
      direction: direction,
      mode: mode,
      orders: [...orders, proposedOrder],
      takeProfits: takeProfits,
      symbol: symbol,
      assetCategory: assetCategory,
      pricePrecision: pricePrecision,
      quantityType: quantityType,
      contractSize: contractSize,
      tickSize: tickSize,
      tickValue: tickValue,
      pipSize: pipSize,
      accountCurrency: accountCurrency,
      currentMarketPrice: currentMarketPrice,
      entryCommissionPercent: entryCommissionPercent,
      exitCommissionPercent: exitCommissionPercent,
      spread: spread,
      slippage: slippage,
      swapCost: swapCost,
      otherCosts: otherCosts,
      bufferValue: bufferValue,
      bufferType: bufferType,
      stopLoss: stopLoss,
      targetRiskReward: targetRiskReward,
      notes: notes,
    );
    final projected = AveragePricePositionEngine(
      projectedSnapshot.toRequest(),
    ).calculate();
    final currentAverage = result.weightedAverageEntry;
    final projectedAverage = projected.weightedAverageEntry;
    if (currentAverage == null || projectedAverage == null) {
      simulationResult = null;
      return;
    }
    final price = Mt5Decimal.tryParse(simulationDraft.price);
    final quantity = Mt5Decimal.tryParse(simulationDraft.quantity);
    final contract = Mt5Decimal.tryParse(contractSize);
    simulationResult = WhatIfCalculationResult(
      current: result,
      projected: projected,
      averageMovement: projectedAverage - currentAverage,
      additionalCapital:
          price * quantity * (contract.isPositive ? contract : Mt5Decimal.one),
    );
  }

  void _scheduleDraftSave() {
    _draftTimer?.cancel();
    _draftTimer = Timer(
      const Duration(milliseconds: 650),
      () => unawaited(_historyStore.saveDraft(userId, snapshot)),
    );
  }

  void _applySnapshot(AveragePriceSetupSnapshot value, {bool external = true}) {
    direction = value.direction;
    mode = value.mode;
    orders = value.orders.isEmpty
        ? [
            PositionOrderDraft(id: _newId('order')),
            PositionOrderDraft(id: _newId('order')),
          ]
        : value.orders
              .map(
                (order) => order.id.isEmpty
                    ? order.copyWith(id: _newId('order'))
                    : order,
              )
              .toList();
    takeProfits = value.takeProfits.isEmpty
        ? [TakeProfitDraft(id: _newId('tp'), percentage: '100')]
        : value.takeProfits
              .map(
                (target) => target.id.isEmpty
                    ? target.copyWith(id: _newId('tp'))
                    : target,
              )
              .toList();
    symbol = value.symbol;
    assetCategory = value.assetCategory;
    pricePrecision = value.pricePrecision;
    quantityType = value.quantityType;
    contractSize = value.contractSize;
    tickSize = value.tickSize;
    tickValue = value.tickValue;
    pipSize = value.pipSize;
    accountCurrency = value.accountCurrency;
    currentMarketPrice = value.currentMarketPrice;
    entryCommissionPercent = value.entryCommissionPercent;
    exitCommissionPercent = value.exitCommissionPercent;
    spread = value.spread;
    slippage = value.slippage;
    swapCost = value.swapCost;
    otherCosts = value.otherCosts;
    bufferValue = value.bufferValue;
    bufferType = value.bufferType;
    stopLoss = value.stopLoss;
    targetRiskReward = value.targetRiskReward;
    notes = value.notes;
    if (external) formEpoch++;
  }

  AveragePriceSetupSnapshot _blankSnapshot() => AveragePriceSetupSnapshot(
    direction: PositionDirection.long,
    mode: AveragePriceMode.simple,
    orders: [
      PositionOrderDraft(id: _newId('order')),
      PositionOrderDraft(id: _newId('order')),
    ],
    takeProfits: [TakeProfitDraft(id: _newId('tp'), percentage: '100')],
    symbol: '',
    assetCategory: AssetCategory.custom,
    pricePrecision: 2,
    quantityType: QuantityType.units,
    contractSize: '',
    tickSize: '',
    tickValue: '',
    pipSize: '',
    accountCurrency: 'USD',
    currentMarketPrice: '',
    entryCommissionPercent: '0',
    exitCommissionPercent: '0',
    spread: '0',
    slippage: '0',
    swapCost: '0',
    otherCosts: '0',
    bufferValue: '0',
    bufferType: SafetyBufferType.points,
    stopLoss: '',
    targetRiskReward: '2',
    notes: '',
  );

  String _newId(String prefix) =>
      '$prefix-${DateTime.now().microsecondsSinceEpoch}-${_idCounter++}';

  @override
  void dispose() {
    _draftTimer?.cancel();
    super.dispose();
  }
}
