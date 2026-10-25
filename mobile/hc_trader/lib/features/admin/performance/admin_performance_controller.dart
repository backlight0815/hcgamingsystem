import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../repositories/trading_repository.dart';
import 'admin_performance_models.dart';

class AdminPerformanceController extends ChangeNotifier {
  AdminPerformanceController({required this.repository});

  final TradingRepository repository;
  AdminPerformanceFilter filter = const AdminPerformanceFilter();
  AdminPerformanceDensity density = AdminPerformanceDensity.comfortable;
  List<AdminTraderPerformance> _records = const [];
  Object? error;
  bool loading = false;
  bool refreshing = false;
  bool exporting = false;
  DateTime? lastUpdated;
  int page = 0;
  int rowsPerPage = 10;
  final Set<String> selectedIds = {};
  Timer? _searchDebounce;
  int _loadGeneration = 0;

  List<AdminTraderPerformance> get allRecords => List.unmodifiable(_records);

  List<AdminTraderPerformance> get filteredRecords {
    final query = filter.search.trim().toLowerCase();
    final records = _records.where((record) {
      if (query.isNotEmpty &&
          !'${record.name} ${record.email} ${record.id}'.toLowerCase().contains(
            query,
          )) {
        return false;
      }
      if (filter.minimumTrades > record.totalTrades) return false;
      if (filter.minimumProfit case final value?
          when record.netProfit < value) {
        return false;
      }
      if (filter.maximumDrawdown case final value?
          when record.maxDrawdownPercent > value) {
        return false;
      }
      if (filter.reviewStatus != 'all' &&
          record.reviewStatus != filter.reviewStatus) {
        return false;
      }
      if (filter.profit == AdminPerformanceProfitFilter.profitable &&
          !record.profitable) {
        return false;
      }
      if (filter.profit == AdminPerformanceProfitFilter.losing &&
          !record.losing) {
        return false;
      }
      if (filter.profit == AdminPerformanceProfitFilter.breakeven &&
          record.netProfit != 0) {
        return false;
      }
      if (filter.risk != AdminPerformanceRiskFilter.all &&
          record.riskLevel != filter.risk.name) {
        return false;
      }
      return true;
    }).toList();
    records.sort(_compare);
    return records;
  }

  List<AdminTraderPerformance> get pageRecords {
    final records = filteredRecords;
    final start = page * rowsPerPage;
    if (start >= records.length) return const [];
    return records.sublist(
      start,
      (start + rowsPerPage).clamp(0, records.length),
    );
  }

  int get pageCount =>
      (filteredRecords.length / rowsPerPage).ceil().clamp(1, 999999);
  AdminPerformanceSummary get summary =>
      AdminPerformanceSummary.fromRecords(filteredRecords);
  List<AdminPerformanceInsight> get insights =>
      buildAdminPerformanceInsights(filteredRecords);
  List<AdminTraderPerformance> get selectedRecords => _records
      .where((record) => selectedIds.contains(record.id))
      .toList(growable: false);

  Future<void> load({bool refresh = false}) async {
    final generation = ++_loadGeneration;
    error = null;
    if (_records.isEmpty) {
      loading = true;
    } else {
      refreshing = true;
    }
    notifyListeners();
    final range = AdminPerformanceDateRange.forPeriod(
      filter.period,
      DateTime.now(),
    );
    try {
      final rows = await repository.adminTraderPerformance(
        startAt: range.start,
        endAt: range.end,
        dataSource: filter.dataSource,
      );
      if (generation != _loadGeneration) return;
      _records = rows.map(AdminTraderPerformance.fromMap).toList();
      lastUpdated = DateTime.now();
      selectedIds.removeWhere(
        (id) => !_records.any((record) => record.id == id),
      );
      page = 0;
    } catch (caught) {
      if (generation == _loadGeneration) error = caught;
    } finally {
      if (generation == _loadGeneration) {
        loading = false;
        refreshing = false;
        notifyListeners();
      }
    }
  }

  void search(String value) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 350), () {
      filter = filter.copyWith(search: value);
      page = 0;
      notifyListeners();
    });
  }

  Future<void> setRemoteFilter(AdminPerformanceFilter value) async {
    final changed =
        value.period != filter.period || value.dataSource != filter.dataSource;
    filter = value;
    page = 0;
    notifyListeners();
    if (changed) await load();
  }

  void setLocalFilter(AdminPerformanceFilter value) {
    filter = value;
    page = 0;
    notifyListeners();
  }

  void clearFilters() {
    final previousSource = filter.dataSource;
    final previousPeriod = filter.period;
    filter = const AdminPerformanceFilter();
    page = 0;
    notifyListeners();
    if (previousSource != filter.dataSource ||
        previousPeriod != filter.period) {
      unawaited(load());
    }
  }

  void toggleSelection(String id, bool selected) {
    selected ? selectedIds.add(id) : selectedIds.remove(id);
    notifyListeners();
  }

  void clearSelection() {
    selectedIds.clear();
    notifyListeners();
  }

  void setPage(int value) {
    page = value.clamp(0, pageCount - 1);
    notifyListeners();
  }

  void setRowsPerPage(int value) {
    rowsPerPage = value;
    page = 0;
    notifyListeners();
  }

  void setDensity(AdminPerformanceDensity value) {
    density = value;
    notifyListeners();
  }

  void setSort(AdminPerformanceSort value) {
    filter = filter.copyWith(sort: value);
    page = 0;
    notifyListeners();
  }

  void setExporting(bool value) {
    exporting = value;
    notifyListeners();
  }

  int _compare(
    AdminTraderPerformance left,
    AdminTraderPerformance right,
  ) => switch (filter.sort) {
    AdminPerformanceSort.profitHigh => right.netProfit.compareTo(
      left.netProfit,
    ),
    AdminPerformanceSort.profitLow => left.netProfit.compareTo(right.netProfit),
    AdminPerformanceSort.returnHigh => right.returnPercent.compareTo(
      left.returnPercent,
    ),
    AdminPerformanceSort.winRateHigh => right.winRate.compareTo(left.winRate),
    AdminPerformanceSort.drawdownLow => left.maxDrawdownPercent.compareTo(
      right.maxDrawdownPercent,
    ),
    AdminPerformanceSort.drawdownHigh => right.maxDrawdownPercent.compareTo(
      left.maxDrawdownPercent,
    ),
    AdminPerformanceSort.scoreHigh => right.overallScore.compareTo(
      left.overallScore,
    ),
    AdminPerformanceSort.mostActive => right.totalTrades.compareTo(
      left.totalTrades,
    ),
    AdminPerformanceSort.leastActive => left.totalTrades.compareTo(
      right.totalTrades,
    ),
    AdminPerformanceSort.recent =>
      (right.lastTradeAt ?? DateTime(1970)).compareTo(
        left.lastTradeAt ?? DateTime(1970),
      ),
    AdminPerformanceSort.ranking => left.rank.compareTo(right.rank),
  };

  @override
  void dispose() {
    _searchDebounce?.cancel();
    super.dispose();
  }
}
