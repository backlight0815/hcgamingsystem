import 'package:cloud_firestore/cloud_firestore.dart';

import 'firestore_helpers.dart';
import 'mt5_import_models.dart';

enum SignalLifecycleStatus {
  draft('draft', 'Draft'),
  scheduled('scheduled', 'Scheduled'),
  published('published', 'Published'),
  pendingEntry('pending_entry', 'Pending entry'),
  active('active', 'Active'),
  partiallyClosed('partially_closed', 'Partially closed'),
  breakEven('break_even', 'Break even'),
  trailing('trailing', 'Trailing'),
  completed('completed', 'Completed'),
  stopLossHit('stop_loss_hit', 'Stop loss hit'),
  breakEvenHit('break_even_hit', 'Break even hit'),
  cancelled('cancelled', 'Cancelled'),
  expired('expired', 'Expired'),
  archived('archived', 'Archived');

  const SignalLifecycleStatus(this.value, this.label);

  final String value;
  final String label;

  bool get isTerminal => const {
    completed,
    stopLossHit,
    breakEvenHit,
    cancelled,
    expired,
  }.contains(this);

  bool get isOpen => const {
    published,
    pendingEntry,
    active,
    partiallyClosed,
    breakEven,
    trailing,
  }.contains(this);

  static SignalLifecycleStatus parse(
    Object? value, {
    Object? legacyStatus,
    bool done = false,
    bool breakEvenHit = false,
    bool breakEvenSet = false,
    bool archived = false,
  }) {
    if (archived) return SignalLifecycleStatus.archived;
    final normalized = value
        ?.toString()
        .trim()
        .toLowerCase()
        .replaceAll('-', '_')
        .replaceAll(' ', '_');
    final SignalLifecycleStatus? alias = switch (normalized) {
      'pending' || 'pending_order' => SignalLifecycleStatus.pendingEntry,
      'activated' => SignalLifecycleStatus.active,
      'partial' || 'partial_close' => SignalLifecycleStatus.partiallyClosed,
      'breakeven' || 'be' => SignalLifecycleStatus.breakEven,
      'closed' || 'done' => SignalLifecycleStatus.completed,
      'stop_loss' || 'sl' || 'loss' => SignalLifecycleStatus.stopLossHit,
      'be_hit' => SignalLifecycleStatus.breakEvenHit,
      'canceled' || 'invalidated' => SignalLifecycleStatus.cancelled,
      'take_profit' || 'tp' || 'win' => SignalLifecycleStatus.completed,
      _ => null,
    };
    if (alias != null) return alias;
    for (final status in values) {
      if (status.value == normalized) return status;
    }
    final code = int.tryParse('$legacyStatus') ?? 0;
    if (done) return completed;
    if (breakEvenHit || code == 15) return SignalLifecycleStatus.breakEvenHit;
    if (code == 12) return cancelled;
    if (code == 13) return stopLossHit;
    if (code >= 2 && code <= 11) return partiallyClosed;
    if (breakEvenSet) return breakEven;
    if (code == 1) return active;
    return draft;
  }
}

enum SignalOrderType {
  marketExecution('market_execution', 'Market execution'),
  buyLimit('buy_limit', 'Buy limit'),
  sellLimit('sell_limit', 'Sell limit'),
  buyStop('buy_stop', 'Buy stop'),
  sellStop('sell_stop', 'Sell stop'),
  pendingOrder('pending_order', 'Pending order');

  const SignalOrderType(this.value, this.label);

  final String value;
  final String label;

  bool get isPending => this != marketExecution;
  bool get isBuy => this == buyLimit || this == buyStop;
  bool get isSell => this == sellLimit || this == sellStop;

  static SignalOrderType parse(Object? value) {
    final normalized = value
        ?.toString()
        .trim()
        .toLowerCase()
        .replaceAll('-', '_')
        .replaceAll(' ', '_');
    return values.where((item) => item.value == normalized).firstOrNull ??
        switch (normalized) {
          'buy_limit_order' => buyLimit,
          'sell_limit_order' => sellLimit,
          'buy_stop_order' => buyStop,
          'sell_stop_order' => sellStop,
          'pending' => pendingOrder,
          _ => marketExecution,
        };
  }
}

enum SignalUpdateType {
  published('published', 'Published'),
  detailsUpdated('details_updated', 'Edit signal details'),
  entryTriggered('entry_triggered', 'Entry triggered'),
  entryPriceUpdated('entry_price_updated', 'Entry price updated'),
  entryRangeUpdated('entry_range_updated', 'Entry range updated'),
  updateStopLoss('update_stop_loss', 'Update stop loss'),
  moveStopLossToBreakEven('move_sl_to_be', 'Move SL to BE'),
  updateBreakEven('update_break_even', 'Update BE price'),
  trailingBreakEven('trailing_break_even', 'Trailing BE'),
  trailingStopLoss('trailing_stop_loss', 'Trailing SL'),
  lockProfit('lock_profit', 'Lock profit'),
  reduceRisk('reduce_risk', 'Reduce risk'),
  removePendingOrder('remove_pending_order', 'Remove pending order'),
  partialClose('partial_close', 'Close partial position'),
  partialProfitTaken('partial_profit_taken', 'Take partial profit'),
  targetHit('target_hit', 'Target hit'),
  stopLossHit('stop_loss_hit', 'SL hit'),
  breakEvenHit('break_even_hit', 'BE hit'),
  manuallyClosed('manually_closed', 'Manually closed'),
  closedInProfit('closed_in_profit', 'Close in profit'),
  closedInLoss('closed_in_loss', 'Close in loss'),
  cancelled('cancelled', 'Cancel signal'),
  expired('expired', 'Expire signal'),
  invalidated('invalidated', 'Invalidate signal'),
  completed('completed', 'Complete signal'),
  commentary('commentary', 'Add signal update'),
  marketCommentary('market_commentary', 'Add market commentary'),
  warning('warning', 'Add warning'),
  chart('chart', 'Add chart image'),
  analysis('analysis', 'Add updated analysis'),
  archived('archived', 'Archive signal'),
  restored('restored', 'Restore signal'),
  correction('correction', 'Correct signal state');

  const SignalUpdateType(this.value, this.label);

  final String value;
  final String label;

  bool get isCommentary => const {
    commentary,
    marketCommentary,
    warning,
    chart,
    analysis,
  }.contains(this);

  bool get requiresReason => const {
    updateStopLoss,
    moveStopLossToBreakEven,
    updateBreakEven,
    trailingBreakEven,
    trailingStopLoss,
    lockProfit,
    reduceRisk,
    removePendingOrder,
    partialClose,
    partialProfitTaken,
    cancelled,
    invalidated,
    manuallyClosed,
    closedInProfit,
    closedInLoss,
    correction,
  }.contains(this);

  static SignalUpdateType parse(Object? value) {
    final normalized = value?.toString().trim().toLowerCase() ?? '';
    return values.where((item) => item.value == normalized).firstOrNull ??
        commentary;
  }
}

class SignalLifecycleSnapshot {
  const SignalLifecycleSnapshot({
    required this.status,
    required this.direction,
    required this.orderType,
    required this.entryPrice,
    required this.currentStopLoss,
    required this.targets,
    required this.targetsHit,
    required this.percentageRemaining,
    required this.version,
    this.statusBeforeArchive,
  });

  factory SignalLifecycleSnapshot.fromMap(Map<String, dynamic> data) {
    final targets = <Mt5Decimal>[];
    for (var index = 1; index <= 20; index++) {
      final parsed = _positiveDecimal(data['target_$index']);
      if (parsed != null) targets.add(parsed);
    }
    if (targets.isEmpty && data['targets'] is Iterable) {
      for (final value in data['targets'] as Iterable) {
        final parsed = _positiveDecimal(value);
        if (parsed != null) targets.add(parsed);
      }
    }
    final targetHits = firestoreIntList(
      data['targets_hit'] ?? data['targetsHit'],
    ).where((item) => item > 0).toSet();
    final status = SignalLifecycleStatus.parse(
      data['lifecycle_status'],
      legacyStatus: data['status'],
      done: firestoreBool(data['is_done']) || firestoreBool(data['IsDone']),
      breakEvenHit: firestoreBool(data['IsBE']),
      breakEvenSet: firestoreBool(data['IsSetBE']),
      archived: firestoreBool(data['archived']),
    );
    return SignalLifecycleSnapshot(
      status: status,
      direction: '${data['immediate_action'] ?? data['direction'] ?? ''}'
          .trim()
          .toUpperCase(),
      orderType: SignalOrderType.parse(data['order_type']),
      entryPrice: _positiveDecimal(
        data['current_entry_price'] ?? data['entry_price'],
      ),
      currentStopLoss: _positiveDecimal(
        data['current_stop_loss'] ?? data['stop_loss'],
      ),
      targets: targets,
      targetsHit: targetHits,
      percentageRemaining:
          _percentage(data['percentage_remaining']) ??
          _percentage(data['remaining_position_percentage']) ??
          const SignalPercentage.full(),
      version: firestoreInt(data['version']).clamp(0, 1 << 30),
      statusBeforeArchive: data['status_before_archive'] == null
          ? null
          : SignalLifecycleStatus.parse(data['status_before_archive']),
    );
  }

  final SignalLifecycleStatus status;
  final String direction;
  final SignalOrderType orderType;
  final Mt5Decimal? entryPrice;
  final Mt5Decimal? currentStopLoss;
  final List<Mt5Decimal> targets;
  final Set<int> targetsHit;
  final SignalPercentage percentageRemaining;
  final int version;
  final SignalLifecycleStatus? statusBeforeArchive;

  bool get isBuy => direction.contains('BUY') || orderType.isBuy;
  bool get isSell => direction.contains('SELL') || orderType.isSell;
  int get nextTargetNumber {
    for (var index = 1; index <= targets.length; index++) {
      if (!targetsHit.contains(index)) return index;
    }
    return targets.length + 1;
  }
}

class SignalPercentage {
  const SignalPercentage._(this.basisPoints);
  const SignalPercentage.full() : basisPoints = 10000;

  factory SignalPercentage.parse(Object? value) {
    final decimal = Mt5Decimal.parse(value);
    final scaled = decimal * Mt5Decimal.fromInt(100);
    final basisPoints = int.parse(scaled.rounded(0).toString());
    if (basisPoints < 0 || basisPoints > 10000) {
      throw const FormatException('Percentage must be between 0 and 100.');
    }
    return SignalPercentage._(basisPoints);
  }

  final int basisPoints;

  bool get isZero => basisPoints == 0;
  double get value => basisPoints / 100;

  SignalPercentage subtract(SignalPercentage other) =>
      SignalPercentage._((basisPoints - other.basisPoints).clamp(0, 10000));

  @override
  String toString() => value.toStringAsFixed(basisPoints % 100 == 0 ? 0 : 2);
}

class SignalUpdateDraft {
  const SignalUpdateDraft({
    required this.idempotencyKey,
    required this.updateType,
    required this.expectedVersion,
    this.titleI18n = const {},
    this.messageI18n = const {},
    this.reason = '',
    this.entryPrice,
    this.entryMin,
    this.entryMax,
    this.newStopLoss,
    this.breakEvenPrice,
    this.targetNumber,
    this.targetPrice,
    this.percentageClosed,
    this.percentageRemaining,
    this.realisedPips,
    this.realisedPercentage,
    this.attachmentUrls = const [],
    this.effectiveAt,
    this.notificationRequested = true,
    this.isCorrection = false,
    this.correctedUpdateId = '',
    this.lifecycleOverride,
    this.primaryLanguage,
    this.profitLockOffset,
    this.entryWasTriggered,
    this.timeframe,
    this.riskLevel,
    this.expiryAt,
  });

  final String idempotencyKey;
  final SignalUpdateType updateType;
  final int expectedVersion;
  final Map<String, String> titleI18n;
  final Map<String, String> messageI18n;
  final String reason;
  final String? entryPrice;
  final String? entryMin;
  final String? entryMax;
  final String? newStopLoss;
  final String? breakEvenPrice;
  final int? targetNumber;
  final String? targetPrice;
  final SignalPercentage? percentageClosed;
  final SignalPercentage? percentageRemaining;
  final String? realisedPips;
  final String? realisedPercentage;
  final List<String> attachmentUrls;
  final DateTime? effectiveAt;
  final bool notificationRequested;
  final bool isCorrection;
  final String correctedUpdateId;
  final SignalLifecycleStatus? lifecycleOverride;
  final String? primaryLanguage;
  final String? profitLockOffset;
  final bool? entryWasTriggered;
  final String? timeframe;
  final String? riskLevel;
  final DateTime? expiryAt;

  bool get hasLocalizedContent => <String>{
    ...titleI18n.values,
    ...messageI18n.values,
  }.any((item) => item.trim().isNotEmpty);

  Set<String> get contentLanguages => <String>{
    for (final entry in {...titleI18n, ...messageI18n}.entries)
      if (entry.value.trim().isNotEmpty)
        entry.key.toLowerCase().startsWith('zh') ? 'zh' : 'en',
  };
}

class SignalUpdateProjection {
  const SignalUpdateProjection({
    required this.from,
    required this.to,
    required this.signalChanges,
    required this.previousValues,
    required this.newValues,
  });

  final SignalLifecycleStatus from;
  final SignalLifecycleStatus to;
  final Map<String, Object?> signalChanges;
  final Map<String, Object?> previousValues;
  final Map<String, Object?> newValues;
}

abstract final class SignalLifecyclePolicy {
  static void validatePublication({
    required String direction,
    required SignalOrderType orderType,
    required Object? entryPrice,
    required Object? stopLoss,
    required Iterable<Object?> targets,
  }) {
    final action = direction.trim().toUpperCase();
    final isBuy = action == 'BUY';
    final isSell = action == 'SELL';
    if (!isBuy && !isSell) {
      throw const SignalLifecycleException('Choose Buy or Sell.');
    }
    if (isBuy && orderType.isSell || isSell && orderType.isBuy) {
      throw const SignalLifecycleException(
        'The pending-order type does not match the signal direction.',
      );
    }
    final entry = _requiredPositive(entryPrice);
    final stop = _requiredPositive(stopLoss);
    final parsedTargets = targets
        .map(_positiveDecimal)
        .whereType<Mt5Decimal>()
        .toList(growable: false);
    if (parsedTargets.isEmpty || parsedTargets.length != targets.length) {
      throw const SignalLifecycleException(
        'Add at least one valid take-profit target.',
      );
    }
    if (isBuy && stop.compareTo(entry) >= 0) {
      throw const SignalLifecycleException(
        'For Buy signals, stop loss must be below entry.',
      );
    }
    if (isSell && stop.compareTo(entry) <= 0) {
      throw const SignalLifecycleException(
        'For Sell signals, stop loss must be above entry.',
      );
    }
    for (var index = 0; index < parsedTargets.length; index++) {
      final target = parsedTargets[index];
      if (isBuy && target.compareTo(entry) <= 0 ||
          isSell && target.compareTo(entry) >= 0) {
        throw const SignalLifecycleException(
          'Take-profit targets must be on the profitable side of entry.',
        );
      }
      if (index > 0 &&
          (isBuy && target.compareTo(parsedTargets[index - 1]) <= 0 ||
              isSell && target.compareTo(parsedTargets[index - 1]) >= 0)) {
        throw const SignalLifecycleException(
          'Take-profit targets must progress away from entry.',
        );
      }
    }
  }

  static const transitions =
      <SignalLifecycleStatus, Set<SignalLifecycleStatus>>{
        SignalLifecycleStatus.draft: {
          SignalLifecycleStatus.scheduled,
          SignalLifecycleStatus.published,
          SignalLifecycleStatus.pendingEntry,
          SignalLifecycleStatus.active,
          SignalLifecycleStatus.cancelled,
        },
        SignalLifecycleStatus.scheduled: {
          SignalLifecycleStatus.published,
          SignalLifecycleStatus.pendingEntry,
          SignalLifecycleStatus.active,
          SignalLifecycleStatus.cancelled,
          SignalLifecycleStatus.expired,
        },
        SignalLifecycleStatus.published: {
          SignalLifecycleStatus.pendingEntry,
          SignalLifecycleStatus.active,
          SignalLifecycleStatus.cancelled,
          SignalLifecycleStatus.expired,
        },
        SignalLifecycleStatus.pendingEntry: {
          SignalLifecycleStatus.active,
          SignalLifecycleStatus.cancelled,
          SignalLifecycleStatus.expired,
        },
        SignalLifecycleStatus.active: {
          SignalLifecycleStatus.partiallyClosed,
          SignalLifecycleStatus.breakEven,
          SignalLifecycleStatus.trailing,
          SignalLifecycleStatus.completed,
          SignalLifecycleStatus.stopLossHit,
          SignalLifecycleStatus.breakEvenHit,
          SignalLifecycleStatus.cancelled,
          SignalLifecycleStatus.expired,
        },
        SignalLifecycleStatus.partiallyClosed: {
          SignalLifecycleStatus.active,
          SignalLifecycleStatus.breakEven,
          SignalLifecycleStatus.trailing,
          SignalLifecycleStatus.completed,
          SignalLifecycleStatus.stopLossHit,
          SignalLifecycleStatus.breakEvenHit,
          SignalLifecycleStatus.cancelled,
        },
        SignalLifecycleStatus.breakEven: {
          SignalLifecycleStatus.partiallyClosed,
          SignalLifecycleStatus.trailing,
          SignalLifecycleStatus.completed,
          SignalLifecycleStatus.stopLossHit,
          SignalLifecycleStatus.breakEvenHit,
          SignalLifecycleStatus.cancelled,
        },
        SignalLifecycleStatus.trailing: {
          SignalLifecycleStatus.partiallyClosed,
          SignalLifecycleStatus.completed,
          SignalLifecycleStatus.stopLossHit,
          SignalLifecycleStatus.breakEvenHit,
          SignalLifecycleStatus.cancelled,
        },
        SignalLifecycleStatus.completed: {SignalLifecycleStatus.archived},
        SignalLifecycleStatus.stopLossHit: {SignalLifecycleStatus.archived},
        SignalLifecycleStatus.breakEvenHit: {SignalLifecycleStatus.archived},
        SignalLifecycleStatus.cancelled: {SignalLifecycleStatus.archived},
        SignalLifecycleStatus.expired: {SignalLifecycleStatus.archived},
        SignalLifecycleStatus.archived: {},
      };

  static bool canTransition(
    SignalLifecycleStatus from,
    SignalLifecycleStatus to, {
    bool correction = false,
    bool superAdmin = false,
  }) =>
      from == to ||
      (correction && superAdmin) ||
      (transitions[from]?.contains(to) ?? false);

  static List<SignalUpdateType> allowedActions(
    SignalLifecycleSnapshot signal, {
    required bool superAdmin,
  }) {
    final actions = switch (signal.status) {
      SignalLifecycleStatus.draft ||
      SignalLifecycleStatus.scheduled => {SignalUpdateType.cancelled},
      SignalLifecycleStatus.published || SignalLifecycleStatus.pendingEntry => {
        SignalUpdateType.entryTriggered,
        SignalUpdateType.entryPriceUpdated,
        SignalUpdateType.entryRangeUpdated,
        SignalUpdateType.cancelled,
        SignalUpdateType.removePendingOrder,
        SignalUpdateType.expired,
      },
      SignalLifecycleStatus.active ||
      SignalLifecycleStatus.partiallyClosed ||
      SignalLifecycleStatus.breakEven ||
      SignalLifecycleStatus.trailing => {
        SignalUpdateType.updateStopLoss,
        SignalUpdateType.moveStopLossToBreakEven,
        SignalUpdateType.updateBreakEven,
        SignalUpdateType.trailingBreakEven,
        SignalUpdateType.trailingStopLoss,
        SignalUpdateType.lockProfit,
        SignalUpdateType.reduceRisk,
        SignalUpdateType.targetHit,
        SignalUpdateType.partialClose,
        SignalUpdateType.partialProfitTaken,
        SignalUpdateType.stopLossHit,
        SignalUpdateType.breakEvenHit,
        SignalUpdateType.manuallyClosed,
        SignalUpdateType.closedInProfit,
        SignalUpdateType.closedInLoss,
        SignalUpdateType.cancelled,
        SignalUpdateType.completed,
      },
      SignalLifecycleStatus.completed ||
      SignalLifecycleStatus.stopLossHit ||
      SignalLifecycleStatus.breakEvenHit ||
      SignalLifecycleStatus.cancelled ||
      SignalLifecycleStatus.expired => <SignalUpdateType>{},
      SignalLifecycleStatus.archived => <SignalUpdateType>{},
    };
    final result = <SignalUpdateType>{
      ...actions,
      if (signal.status != SignalLifecycleStatus.archived) ...{
        SignalUpdateType.commentary,
        SignalUpdateType.marketCommentary,
        SignalUpdateType.warning,
        SignalUpdateType.chart,
        SignalUpdateType.analysis,
      },
      if (!signal.status.isTerminal &&
          signal.status != SignalLifecycleStatus.archived)
        SignalUpdateType.detailsUpdated,
      if (superAdmin) SignalUpdateType.correction,
      if (superAdmin && signal.status.isTerminal) SignalUpdateType.archived,
      if (superAdmin && signal.status == SignalLifecycleStatus.archived)
        SignalUpdateType.restored,
    };
    return result.toList(growable: false);
  }

  static SignalUpdateProjection project(
    SignalLifecycleSnapshot signal,
    SignalUpdateDraft draft, {
    required bool superAdmin,
  }) {
    _validateDraft(signal, draft, superAdmin: superAdmin);
    final next = _nextStatus(signal, draft);
    if (!canTransition(
      signal.status,
      next,
      correction:
          draft.isCorrection ||
          draft.updateType == SignalUpdateType.correction ||
          draft.updateType == SignalUpdateType.restored,
      superAdmin: superAdmin,
    )) {
      throw SignalLifecycleException(
        'Invalid transition from ${signal.status.label} to ${next.label}.',
      );
    }

    final changes = <String, Object?>{
      'lifecycle_status': next.value,
      'version': signal.version + 1,
      'status': _legacyStatus(next, draft.targetNumber),
      'archived': next == SignalLifecycleStatus.archived,
    };
    if (draft.updateType == SignalUpdateType.detailsUpdated) {
      final language =
          draft.primaryLanguage?.toLowerCase().startsWith('zh') == true
          ? 'zh'
          : 'en';
      final secondary = language == 'zh' ? 'en' : 'zh';
      final title =
          (draft.titleI18n[language] ?? draft.titleI18n[secondary] ?? '')
              .trim();
      if (title.isNotEmpty) {
        changes['signal_title'] = title;
        changes['signal_title_i18n'] = draft.titleI18n;
        changes['title_i18n'] = draft.titleI18n;
      }
      if (draft.timeframe?.trim().isNotEmpty == true) {
        changes['timeframe'] = draft.timeframe!.trim();
      }
      if (draft.riskLevel?.trim().isNotEmpty == true) {
        changes['risk_level'] = draft.riskLevel!.trim();
      }
      if (draft.expiryAt != null) {
        changes['expiry_time'] = Timestamp.fromDate(draft.expiryAt!);
      }
    }
    final before = <String, Object?>{
      'lifecycle_status': signal.status.value,
      'current_stop_loss': signal.currentStopLoss?.toString(),
      'percentage_remaining': signal.percentageRemaining.value,
      'targets_hit': signal.targetsHit.toList()..sort(),
      'version': signal.version,
    };

    if (draft.entryPrice != null) {
      changes['entry_price'] = _requiredPositive(draft.entryPrice).toString();
      changes['current_entry_price'] = changes['entry_price'];
    }
    if (draft.entryMin != null) {
      changes['entry_min'] = _requiredPositive(draft.entryMin).toString();
    }
    if (draft.entryMax != null) {
      changes['entry_max'] = _requiredPositive(draft.entryMax).toString();
    }
    if (draft.newStopLoss != null) {
      final stop = _requiredPositive(draft.newStopLoss).toString();
      changes['stop_loss'] = stop;
      changes['current_stop_loss'] = stop;
    }
    if (draft.breakEvenPrice != null) {
      final price = _requiredPositive(draft.breakEvenPrice).toString();
      changes['break_even_price'] = price;
      if (draft.updateType == SignalUpdateType.moveStopLossToBreakEven ||
          draft.updateType == SignalUpdateType.trailingBreakEven) {
        changes['stop_loss'] = price;
        changes['current_stop_loss'] = price;
      }
      changes['IsSetBE'] = 1;
    }
    if (draft.updateType == SignalUpdateType.entryTriggered) {
      changes['activated_at'] = FieldValue.serverTimestamp();
    }
    if (draft.targetNumber != null) {
      final hits = {...signal.targetsHit, draft.targetNumber!}.toList()..sort();
      changes['targets_hit'] = hits;
      changes['current_target_index'] = draft.targetNumber;
    }
    final remaining =
        draft.percentageRemaining ??
        (draft.percentageClosed == null
            ? signal.percentageRemaining
            : signal.percentageRemaining.subtract(draft.percentageClosed!));
    changes['percentage_remaining'] = remaining.value;
    if (next.isTerminal) {
      changes['is_done'] = 1;
      changes['completed_at'] = FieldValue.serverTimestamp();
    }
    if (next == SignalLifecycleStatus.cancelled) {
      changes['cancelled_at'] = FieldValue.serverTimestamp();
    }
    if (next == SignalLifecycleStatus.expired) {
      changes['expired_at'] = FieldValue.serverTimestamp();
    }
    if (next == SignalLifecycleStatus.archived) {
      changes['status_before_archive'] = signal.status.value;
      changes['archived_at'] = FieldValue.serverTimestamp();
    }
    if (draft.updateType == SignalUpdateType.restored) {
      changes['archived'] = false;
      changes['archived_at'] = FieldValue.delete();
      changes['status_before_archive'] = FieldValue.delete();
      changes['is_done'] = next.isTerminal ? 1 : 0;
    }

    return SignalUpdateProjection(
      from: signal.status,
      to: next,
      signalChanges: changes,
      previousValues: before,
      newValues: {
        'lifecycle_status': next.value,
        'current_stop_loss':
            changes['current_stop_loss'] ?? signal.currentStopLoss?.toString(),
        'break_even_price': changes['break_even_price'],
        'percentage_remaining': remaining.value,
        'targets_hit': changes['targets_hit'] ?? before['targets_hit'],
        'version': signal.version + 1,
      },
    );
  }

  static void _validateDraft(
    SignalLifecycleSnapshot signal,
    SignalUpdateDraft draft, {
    required bool superAdmin,
  }) {
    if (draft.idempotencyKey.trim().isEmpty) {
      throw const SignalLifecycleException('Update ID is required.');
    }
    if (draft.expectedVersion != signal.version) {
      throw SignalConflictException(
        expectedVersion: draft.expectedVersion,
        actualVersion: signal.version,
      );
    }
    if (!allowedActions(
      signal,
      superAdmin: superAdmin,
    ).contains(draft.updateType)) {
      throw SignalLifecycleException(
        '${draft.updateType.label} is unavailable for ${signal.status.label}.',
      );
    }
    if (draft.updateType.isCommentary && !draft.hasLocalizedContent) {
      throw const SignalLifecycleException(
        'Enter an update in at least one language.',
      );
    }
    if (draft.updateType == SignalUpdateType.detailsUpdated &&
        draft.titleI18n.values.every((value) => value.trim().isEmpty) &&
        draft.timeframe?.trim().isEmpty != false &&
        draft.riskLevel?.trim().isEmpty != false &&
        draft.expiryAt == null) {
      throw const SignalLifecycleException(
        'Change at least one signal detail.',
      );
    }
    if (draft.updateType.requiresReason && draft.reason.trim().isEmpty) {
      throw const SignalLifecycleException('A reason is required.');
    }
    if ((draft.isCorrection ||
            draft.updateType == SignalUpdateType.correction) &&
        !superAdmin) {
      throw const SignalLifecycleException(
        'Only a Super Admin can submit a correction.',
      );
    }
    if (draft.updateType == SignalUpdateType.correction &&
        draft.lifecycleOverride == null) {
      throw const SignalLifecycleException(
        'Choose the corrected lifecycle status.',
      );
    }
    if (const {
      SignalUpdateType.updateStopLoss,
      SignalUpdateType.trailingStopLoss,
      SignalUpdateType.lockProfit,
      SignalUpdateType.reduceRisk,
    }.contains(draft.updateType)) {
      final nextStop = _requiredPositive(draft.newStopLoss);
      final previousStop = signal.currentStopLoss;
      final increasesRisk =
          previousStop != null &&
          (signal.isBuy && nextStop.compareTo(previousStop) < 0 ||
              signal.isSell && nextStop.compareTo(previousStop) > 0);
      if (increasesRisk && !(superAdmin && draft.isCorrection)) {
        throw const SignalLifecycleException(
          'The new stop loss increases risk. Use the Super Admin correction flow.',
        );
      }
    }
    if (const {
      SignalUpdateType.moveStopLossToBreakEven,
      SignalUpdateType.updateBreakEven,
      SignalUpdateType.trailingBreakEven,
    }.contains(draft.updateType)) {
      _requiredPositive(draft.breakEvenPrice);
    }
    if (draft.updateType == SignalUpdateType.targetHit) {
      final targetNumber = draft.targetNumber;
      if (targetNumber == null ||
          targetNumber < 1 ||
          targetNumber > signal.targets.length) {
        throw const SignalLifecycleException('Choose a valid target number.');
      }
      if (signal.targetsHit.contains(targetNumber)) {
        throw const SignalLifecycleException(
          'This target is already marked hit.',
        );
      }
      if (targetNumber != signal.nextTargetNumber && !draft.isCorrection) {
        throw SignalLifecycleException(
          'TP${signal.nextTargetNumber} must be recorded before TP$targetNumber.',
        );
      }
      if (draft.targetPrice != null) _requiredPositive(draft.targetPrice);
    }
    if (draft.updateType == SignalUpdateType.entryRangeUpdated) {
      final minimum = _requiredPositive(draft.entryMin);
      final maximum = _requiredPositive(draft.entryMax);
      if (minimum.compareTo(maximum) > 0) {
        throw const SignalLifecycleException(
          'Entry range minimum cannot exceed its maximum.',
        );
      }
    }
    if (draft.profitLockOffset?.trim().isNotEmpty == true) {
      _requiredPositive(draft.profitLockOffset);
    }
    if (draft.percentageClosed != null && draft.percentageClosed!.isZero) {
      throw const SignalLifecycleException(
        'Closed percentage must be greater than zero.',
      );
    }
    if (draft.percentageClosed != null &&
        draft.percentageClosed!.basisPoints >
            signal.percentageRemaining.basisPoints) {
      throw const SignalLifecycleException(
        'Closed percentage cannot exceed the remaining position.',
      );
    }
    if (draft.percentageRemaining != null &&
        draft.percentageRemaining!.basisPoints >
            signal.percentageRemaining.basisPoints &&
        !(superAdmin && draft.isCorrection)) {
      throw const SignalLifecycleException(
        'Remaining position cannot increase without a Super Admin correction.',
      );
    }
  }

  static SignalLifecycleStatus _nextStatus(
    SignalLifecycleSnapshot signal,
    SignalUpdateDraft draft,
  ) {
    if (draft.updateType == SignalUpdateType.correction) {
      return draft.lifecycleOverride!;
    }
    if (draft.updateType == SignalUpdateType.restored) {
      return signal.statusBeforeArchive ?? SignalLifecycleStatus.draft;
    }
    return switch (draft.updateType) {
      SignalUpdateType.entryTriggered => SignalLifecycleStatus.active,
      SignalUpdateType.moveStopLossToBreakEven ||
      SignalUpdateType.updateBreakEven =>
        signal.status == SignalLifecycleStatus.trailing
            ? SignalLifecycleStatus.trailing
            : SignalLifecycleStatus.breakEven,
      SignalUpdateType.trailingBreakEven ||
      SignalUpdateType.trailingStopLoss ||
      SignalUpdateType.lockProfit => SignalLifecycleStatus.trailing,
      SignalUpdateType.partialClose || SignalUpdateType.partialProfitTaken =>
        SignalLifecycleStatus.partiallyClosed,
      SignalUpdateType.targetHit =>
        draft.targetNumber == signal.targets.length ||
                draft.percentageRemaining?.isZero == true
            ? SignalLifecycleStatus.completed
            : SignalLifecycleStatus.partiallyClosed,
      SignalUpdateType.stopLossHit => SignalLifecycleStatus.stopLossHit,
      SignalUpdateType.breakEvenHit => SignalLifecycleStatus.breakEvenHit,
      SignalUpdateType.manuallyClosed ||
      SignalUpdateType.closedInProfit ||
      SignalUpdateType.closedInLoss ||
      SignalUpdateType.completed => SignalLifecycleStatus.completed,
      SignalUpdateType.cancelled ||
      SignalUpdateType.removePendingOrder ||
      SignalUpdateType.invalidated => SignalLifecycleStatus.cancelled,
      SignalUpdateType.expired => SignalLifecycleStatus.expired,
      SignalUpdateType.archived => SignalLifecycleStatus.archived,
      _ => signal.status,
    };
  }

  static int _legacyStatus(SignalLifecycleStatus status, int? targetNumber) =>
      switch (status) {
        SignalLifecycleStatus.draft ||
        SignalLifecycleStatus.scheduled ||
        SignalLifecycleStatus.published ||
        SignalLifecycleStatus.pendingEntry => 0,
        SignalLifecycleStatus.active ||
        SignalLifecycleStatus.breakEven ||
        SignalLifecycleStatus.trailing => 1,
        SignalLifecycleStatus.partiallyClosed =>
          ((targetNumber ?? 1) + 1).clamp(2, 11),
        SignalLifecycleStatus.cancelled || SignalLifecycleStatus.expired => 12,
        SignalLifecycleStatus.stopLossHit => 13,
        SignalLifecycleStatus.breakEvenHit => 15,
        SignalLifecycleStatus.completed || SignalLifecycleStatus.archived =>
          ((targetNumber ?? 10) + 1).clamp(2, 11),
      };
}

class TradingSignalUpdate {
  const TradingSignalUpdate({
    required this.id,
    required this.updateType,
    required this.lifecycleFrom,
    required this.lifecycleTo,
    required this.titleI18n,
    required this.messageI18n,
    required this.previousValues,
    required this.newValues,
    required this.createdBy,
    required this.createdByName,
    required this.createdByRole,
    required this.reason,
    required this.attachmentUrls,
    required this.notificationRequested,
    required this.notificationSent,
    required this.isCorrection,
    required this.correctedUpdateId,
    this.targetNumber,
    this.targetPrice,
    this.percentageClosed,
    this.percentageRemaining,
    this.realisedPips,
    this.realisedPercentage,
    this.effectiveAt,
    this.createdAt,
  });

  factory TradingSignalUpdate.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> document,
  ) {
    final data = document.data() ?? const <String, dynamic>{};
    return TradingSignalUpdate(
      id: document.id,
      updateType: SignalUpdateType.parse(data['update_type']),
      lifecycleFrom: SignalLifecycleStatus.parse(data['lifecycle_from']),
      lifecycleTo: SignalLifecycleStatus.parse(data['lifecycle_to']),
      titleI18n: firestoreStringMap(data['title_i18n']),
      messageI18n: firestoreStringMap(data['message_i18n']),
      previousValues: _objectMap(data['previous_values']),
      newValues: _objectMap(data['new_values']),
      targetNumber: data['target_number'] == null
          ? null
          : firestoreInt(data['target_number']),
      targetPrice: data['target_price']?.toString(),
      percentageClosed: data['percentage_closed'] == null
          ? null
          : double.tryParse('${data['percentage_closed']}'),
      percentageRemaining: data['percentage_remaining'] == null
          ? null
          : double.tryParse('${data['percentage_remaining']}'),
      realisedPips: data['realised_pips']?.toString(),
      realisedPercentage: data['realised_percentage']?.toString(),
      reason: '${data['reason'] ?? ''}',
      attachmentUrls: firestoreStringList(data['attachment_urls']),
      effectiveAt: firestoreDate(data['effective_at']),
      createdAt: firestoreDate(data['created_at']),
      createdBy: '${data['created_by'] ?? ''}',
      createdByName: '${data['created_by_name'] ?? ''}',
      createdByRole: '${data['created_by_role'] ?? ''}',
      notificationRequested: firestoreBool(data['notification_requested']),
      notificationSent: firestoreBool(data['notification_sent']),
      isCorrection: firestoreBool(data['is_correction']),
      correctedUpdateId: '${data['corrected_update_id'] ?? ''}',
    );
  }

  final String id;
  final SignalUpdateType updateType;
  final SignalLifecycleStatus lifecycleFrom;
  final SignalLifecycleStatus lifecycleTo;
  final Map<String, String> titleI18n;
  final Map<String, String> messageI18n;
  final Map<String, Object?> previousValues;
  final Map<String, Object?> newValues;
  final int? targetNumber;
  final String? targetPrice;
  final double? percentageClosed;
  final double? percentageRemaining;
  final String? realisedPips;
  final String? realisedPercentage;
  final String reason;
  final List<String> attachmentUrls;
  final DateTime? effectiveAt;
  final DateTime? createdAt;
  final String createdBy;
  final String createdByName;
  final String createdByRole;
  final bool notificationRequested;
  final bool notificationSent;
  final bool isCorrection;
  final String correctedUpdateId;
}

class SignalOperationsMetrics {
  const SignalOperationsMetrics({
    required this.total,
    required this.active,
    required this.pendingEntry,
    required this.recentTargetHits,
    required this.nearExpiry,
    required this.stale,
    required this.completedToday,
    required this.cancelled,
  });

  const SignalOperationsMetrics.empty()
    : total = 0,
      active = 0,
      pendingEntry = 0,
      recentTargetHits = 0,
      nearExpiry = 0,
      stale = 0,
      completedToday = 0,
      cancelled = 0;

  final int total;
  final int active;
  final int pendingEntry;
  final int recentTargetHits;
  final int nearExpiry;
  final int stale;
  final int completedToday;
  final int cancelled;

  int get attention => pendingEntry + nearExpiry + stale;
}

class SignalLifecycleException implements Exception {
  const SignalLifecycleException(this.message);
  final String message;

  @override
  String toString() => message;
}

class SignalConflictException extends SignalLifecycleException {
  SignalConflictException({
    required this.expectedVersion,
    required this.actualVersion,
  }) : super(
         'This signal changed while you were editing it. Refresh and try again.',
       );

  final int expectedVersion;
  final int actualVersion;
}

Mt5Decimal? _positiveDecimal(Object? value) {
  try {
    final parsed = Mt5Decimal.parse(value);
    return parsed.isPositive ? parsed : null;
  } on FormatException {
    return null;
  }
}

Mt5Decimal _requiredPositive(Object? value) {
  final parsed = _positiveDecimal(value);
  if (parsed == null) {
    throw const SignalLifecycleException('Enter a valid positive price.');
  }
  return parsed;
}

SignalPercentage? _percentage(Object? value) {
  if (value == null || '$value'.trim().isEmpty) return null;
  try {
    return SignalPercentage.parse(value);
  } on FormatException {
    return null;
  }
}

Map<String, Object?> _objectMap(Object? value) =>
    value is Map ? value.map((key, item) => MapEntry('$key', item)) : const {};
