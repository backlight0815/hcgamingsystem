import '../core/security/app_role_permissions.dart';
import '../models/app_user.dart';
import '../models/trade_call_statistics.dart';
import '../models/trading_signal_item.dart';

enum _CallOutcome { win, loss, breakEven, none }

class TradeCallStatisticsService {
  const TradeCallStatisticsService({this.now});

  final DateTime Function()? now;

  TradeCallStatistics calculate({
    required Iterable<TradingSignalItem> calls,
    required AppUser viewer,
    TradeCallStatisticsFilter filter = const TradeCallStatisticsFilter(),
  }) {
    final isAdministrator = viewer.effectiveRoleIds.any(
      const {
        RolePermissionMatrix.superAdmin,
        RolePermissionMatrix.subAdmin,
        RolePermissionMatrix.founder,
        RolePermissionMatrix.signalProviderManagement,
      }.contains,
    );
    final isProvider = viewer.effectiveRoleIds.any(
      const {
        RolePermissionMatrix.signalProvider,
        RolePermissionMatrix.seniorSignalProvider,
      }.contains,
    );
    if (!isAdministrator && !isProvider) {
      throw const TradeCallAccessException(
        'Trade-call statistics are restricted to administrators and signal providers.',
      );
    }
    final requestedProvider = filter.providerId.trim();
    if (isProvider &&
        requestedProvider.isNotEmpty &&
        requestedProvider != viewer.uid) {
      throw const TradeCallAccessException(
        'Signal providers can view only their own statistics.',
      );
    }
    final lockedProvider = isProvider ? viewer.uid : requestedProvider;
    final currentTime = now?.call() ?? DateTime.now();
    final selected = calls
        .where((call) {
          if (isProvider && call.providerId.trim().isEmpty) return false;
          if (lockedProvider.isNotEmpty && call.providerId != lockedProvider) {
            return false;
          }
          final eventDate = _eventDate(call);
          if (eventDate != null && !filter.dateFilter.matches(eventDate)) {
            return false;
          }
          if (filter.instrument.isNotEmpty &&
              call.tradingPair.toUpperCase() !=
                  filter.instrument.toUpperCase()) {
            return false;
          }
          if (filter.signalSource.isNotEmpty &&
              call.signalSource.toLowerCase() !=
                  filter.signalSource.toLowerCase()) {
            return false;
          }
          if (filter.direction.isNotEmpty &&
              call.direction.toLowerCase() != filter.direction.toLowerCase()) {
            return false;
          }
          if (filter.orderType.isNotEmpty &&
              call.orderType.toLowerCase() != filter.orderType.toLowerCase()) {
            return false;
          }
          if (filter.status.isNotEmpty &&
              _category(call, currentTime) != filter.status.toLowerCase()) {
            return false;
          }
          return true;
        })
        .toList(growable: false);

    final categoryCounts = <String, int>{};
    final evaluated = <TradingSignalItem>[];
    final outcomes = <String, _CallOutcome>{};
    final plannedRatios = <double>[];
    final realizedRatios = <double>[];
    final pips = <double>[];
    final durations = <Duration>[];
    final providerNames = <String, String>{};

    for (final call in selected) {
      final category = _category(call, currentTime);
      categoryCounts[category] = (categoryCounts[category] ?? 0) + 1;
      if (call.providerId.isNotEmpty) {
        providerNames[call.providerId] = call.analyst.trim().isEmpty
            ? call.providerId
            : call.analyst.trim();
      }
      final outcome = _outcome(call, currentTime);
      outcomes[call.id] = outcome;
      if (outcome == _CallOutcome.none) continue;
      evaluated.add(call);
      final planned = call.riskRewardRatio;
      if (planned != null && planned.isFinite && planned > 0) {
        plannedRatios.add(planned);
      }
      final realized = call.realizedRiskReward;
      if (realized != null && realized.isFinite) realizedRatios.add(realized);
      final pipValue = call.pipsResult?.toDouble() ?? _calculatedPips(call);
      if (pipValue != null && pipValue.isFinite) pips.add(pipValue);
      final start = call.activatedAt ?? call.triggerTime;
      final end = call.closedAt ?? call.updatedAt;
      if (start != null && end != null && end.isAfter(start)) {
        durations.add(end.difference(start));
      }
    }

    int outcomeCount(_CallOutcome outcome) =>
        outcomes.values.where((item) => item == outcome).length;
    final wins = outcomeCount(_CallOutcome.win);
    final losses = outcomeCount(_CallOutcome.loss);
    final breakEvens = outcomeCount(_CallOutcome.breakEven);
    final completed = [...evaluated]
      ..sort(
        (left, right) => (_eventDate(right) ?? DateTime(1900)).compareTo(
          _eventDate(left) ?? DateTime(1900),
        ),
      );

    return TradeCallStatistics(
      totalCalls: selected.length,
      draftCalls: categoryCounts['draft'] ?? 0,
      pendingCalls: categoryCounts['pending'] ?? 0,
      activeCalls: categoryCounts['active'] ?? 0,
      closedCalls: evaluated.length,
      cancelledCalls: categoryCounts['cancelled'] ?? 0,
      expiredCalls: categoryCounts['expired'] ?? 0,
      takeProfitCalls: wins,
      stopLossCalls: losses,
      breakEvenCalls: breakEvens,
      winningCalls: wins,
      losingCalls: losses,
      evaluatedCalls: evaluated.length,
      excludedFromRates: selected.length - evaluated.length,
      averagePlannedRiskReward: _average(plannedRatios),
      averageRealizedRiskReward: _average(realizedRatios),
      totalPips: _sum(pips),
      averagePips: _average(pips),
      totalPositivePips: _sum(pips.where((value) => value > 0)),
      totalNegativePips: _sum(pips.where((value) => value < 0)),
      averageHoldingDuration: _averageDuration(durations),
      shortestHoldingDuration: durations.isEmpty
          ? null
          : durations.reduce((left, right) => left < right ? left : right),
      longestHoldingDuration: durations.isEmpty
          ? null
          : durations.reduce((left, right) => left > right ? left : right),
      monthlyPerformance: _breakdown(evaluated, outcomes, (call) {
        final date = _eventDate(call)!;
        return '${date.year}-${date.month.toString().padLeft(2, '0')}';
      }),
      weeklyPerformance: _breakdown(
        evaluated,
        outcomes,
        (call) => _weekKey(_eventDate(call)!),
      ),
      byInstrument: _breakdown(
        evaluated,
        outcomes,
        (call) => call.tradingPair.toUpperCase().trim().isEmpty
            ? 'Unknown'
            : call.tradingPair.toUpperCase().trim(),
      ),
      byDirection: _breakdown(evaluated, outcomes, (call) => call.direction),
      byOrderType: _breakdown(
        evaluated,
        outcomes,
        (call) =>
            call.orderType.trim().isEmpty ? 'Unspecified' : call.orderType,
      ),
      bySignalSource: _breakdown(
        evaluated,
        outcomes,
        (call) => call.signalSource.trim().isEmpty
            ? 'Unspecified'
            : call.signalSource,
      ),
      byProvider: _breakdown(
        evaluated.where((call) => call.providerId.isNotEmpty),
        outcomes,
        (call) => call.providerId,
        labels: providerNames,
      ),
      providerNames: providerNames,
      recentCompletedCallIds: completed
          .take(12)
          .map((call) => call.id)
          .toList(growable: false),
      missingProviderCount: selected
          .where((call) => call.providerId.trim().isEmpty)
          .length,
    );
  }

  static double pipSizeForSymbol(String symbol) {
    final normalized = symbol.toUpperCase().replaceAll(
      RegExp(r'[^A-Z0-9]'),
      '',
    );
    if (normalized.contains('JPY')) return .01;
    if (normalized.startsWith('XAU')) return .1;
    if (normalized.startsWith('XAG')) return .01;
    if (normalized.contains('BTC') || normalized.contains('ETH')) return 1;
    if (RegExp(r'(US30|NAS|SPX|GER|UK100|HK50)').hasMatch(normalized)) {
      return 1;
    }
    return .0001;
  }

  static double calculatePips({
    required double entry,
    required double exit,
    required String symbol,
    required String direction,
  }) {
    final delta = direction.toLowerCase() == 'sell'
        ? entry - exit
        : exit - entry;
    return delta / pipSizeForSymbol(symbol);
  }

  _CallOutcome _outcome(TradingSignalItem call, DateTime currentTime) {
    if (_excluded(call, currentTime)) return _CallOutcome.none;
    final status = call.lifecycleStatus.trim().toLowerCase();
    if (call.isBreakEven ||
        call.status == 15 ||
        const {'break_even', 'breakeven', 'be'}.contains(status)) {
      return _CallOutcome.breakEven;
    }
    if ((call.status >= 2 && call.status <= 11) ||
        const {'take_profit', 'tp', 'win', 'won'}.contains(status)) {
      return _CallOutcome.win;
    }
    if (call.status == 13 ||
        const {'stop_loss', 'sl', 'loss', 'lost'}.contains(status)) {
      return _CallOutcome.loss;
    }
    if (call.isDone || const {'closed', 'completed'}.contains(status)) {
      final pips = call.pipsResult;
      if (pips == null || pips == 0) return _CallOutcome.breakEven;
      return pips > 0 ? _CallOutcome.win : _CallOutcome.loss;
    }
    return _CallOutcome.none;
  }

  bool _excluded(TradingSignalItem call, DateTime currentTime) {
    final status = call.lifecycleStatus.trim().toLowerCase();
    if (call.isTest || call.isDeleted || call.status == 12) return true;
    if (const {
      'draft',
      'cancelled',
      'canceled',
      'rejected',
      'duplicate',
      'invalid',
      'test',
      'deleted',
    }.contains(status)) {
      return true;
    }
    final neverActivated = call.activatedAt == null && call.triggerTime == null;
    if (neverActivated &&
        (status == 'expired' ||
            (call.expiryTime != null &&
                currentTime.isAfter(call.expiryTime!)))) {
      return true;
    }
    final numericOutcome =
        (call.status >= 2 && call.status <= 11) ||
        call.status == 13 ||
        call.status == 15;
    if (numericOutcome || call.isDone || call.isBreakEven) return false;
    return const {
      'pending',
      'submitted',
      'active',
      'activated',
    }.contains(status);
  }

  String _category(TradingSignalItem call, DateTime currentTime) {
    final status = call.lifecycleStatus.trim().toLowerCase();
    if (call.isTest ||
        call.isDeleted ||
        const {
          'duplicate',
          'invalid',
          'rejected',
          'deleted',
          'test',
        }.contains(status)) {
      return 'excluded';
    }
    if (status == 'draft') return 'draft';
    if (call.status == 12 || const {'cancelled', 'canceled'}.contains(status)) {
      return 'cancelled';
    }
    if (status == 'expired' ||
        (call.expiryTime != null &&
            call.activatedAt == null &&
            call.triggerTime == null &&
            currentTime.isAfter(call.expiryTime!))) {
      return 'expired';
    }
    if (_outcome(call, currentTime) != _CallOutcome.none) return 'closed';
    if (call.status == 1 ||
        call.activatedAt != null ||
        call.triggerTime != null ||
        const {'active', 'activated'}.contains(status)) {
      return 'active';
    }
    return 'pending';
  }

  DateTime? _eventDate(TradingSignalItem call) =>
      call.closedAt ??
      call.updatedAt ??
      call.activatedAt ??
      call.triggerTime ??
      call.createdAt;

  double? _calculatedPips(TradingSignalItem call) {
    final entry = _number(call.entryPrice);
    final exit = call.actualExitPrice ?? _number(call.currentPrice);
    if (entry == null || exit == null) return null;
    return calculatePips(
      entry: entry,
      exit: exit,
      symbol: call.tradingPair,
      direction: call.direction,
    );
  }

  double? _number(String value) {
    final match = RegExp(
      r'-?\d+(?:\.\d+)?',
    ).firstMatch(value.replaceAll(',', ''));
    return match == null ? null : double.tryParse(match.group(0)!);
  }

  List<TradeCallBreakdown> _breakdown(
    Iterable<TradingSignalItem> calls,
    Map<String, _CallOutcome> outcomes,
    String Function(TradingSignalItem) keyFor, {
    Map<String, String> labels = const {},
  }) {
    final groups = <String, List<TradingSignalItem>>{};
    for (final call in calls) {
      groups.putIfAbsent(keyFor(call), () => []).add(call);
    }
    final values = groups.entries.map((entry) {
      final callPips = entry.value
          .map((call) => call.pipsResult?.toDouble() ?? _calculatedPips(call))
          .whereType<double>();
      return TradeCallBreakdown(
        key: entry.key,
        label: labels[entry.key] ?? entry.key,
        total: entry.value.length,
        wins: entry.value
            .where((call) => outcomes[call.id] == _CallOutcome.win)
            .length,
        losses: entry.value
            .where((call) => outcomes[call.id] == _CallOutcome.loss)
            .length,
        breakEvens: entry.value
            .where((call) => outcomes[call.id] == _CallOutcome.breakEven)
            .length,
        totalPips: _sum(callPips),
      );
    }).toList();
    values.sort((left, right) => right.total.compareTo(left.total));
    return values;
  }

  String _weekKey(DateTime value) {
    final day = DateTime(value.year, value.month, value.day);
    final thursday = day.add(Duration(days: 4 - day.weekday));
    final first = DateTime(thursday.year, 1, 1);
    final week = 1 + thursday.difference(first).inDays ~/ 7;
    return '${thursday.year}-W${week.toString().padLeft(2, '0')}';
  }

  double _sum(Iterable<double> values) =>
      values.fold<double>(0, (sum, value) => sum + value);

  double _average(Iterable<double> values) {
    final list = values.toList(growable: false);
    return list.isEmpty ? 0 : _sum(list) / list.length;
  }

  Duration? _averageDuration(List<Duration> values) => values.isEmpty
      ? null
      : Duration(
          milliseconds:
              values.fold<int>(0, (sum, value) => sum + value.inMilliseconds) ~/
              values.length,
        );
}
