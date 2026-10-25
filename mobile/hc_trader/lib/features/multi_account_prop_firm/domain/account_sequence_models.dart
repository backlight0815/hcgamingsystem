import 'prop_firm_models.dart';

enum SequenceMode {
  healthiestNext,
  fixedRotation,
  payoutProtection,
  recoveryLast,
  leastRecentlyUsed,
  custom,
}

enum SequenceAccountRole {
  starting,
  firstBackup,
  secondBackup,
  recovery,
  payoutProtected,
  emergencyOnly,
  unavailable,
}

enum SequenceStepStatus {
  pending,
  active,
  completed,
  skipped,
  blocked,
  stopped,
}

enum SequenceTiePreference {
  showAlternatives,
  leastRecentlyUsed,
  largestDailyLossBuffer,
  largestOverallLossBuffer,
  customOrder,
}

enum SequenceAfterTpAction {
  moveToNext,
  keepAfterCooldown,
  protectForDay,
  stopAfterDailyTarget,
  recalculate,
}

enum SequenceAfterSlAction {
  lockForDay,
  startCooldown,
  psychologyCheckThenNext,
  stopPortfolio,
  recalculateRemainingRisk,
}

enum SequenceAfterBreakevenAction {
  keepCurrent,
  moveToNext,
  doNotCountStep,
  recalculate,
}

enum DataCompleteness {
  completeAccountInformation,
  partiallyCompleteAccountInformation,
  insufficientAccountInformation,
}

class SequenceReason {
  const SequenceReason(this.messageKey, [this.values = const {}]);

  final String messageKey;
  final Map<String, Object?> values;

  factory SequenceReason.fromJson(Map<String, dynamic> json) => SequenceReason(
    '${json['message_key'] ?? ''}',
    Map<String, Object?>.from(json['values'] as Map? ?? const {}),
  );

  Map<String, Object?> toJson() => {
    'message_key': messageKey,
    'values': values,
  };
}

class HealthScoreComponent {
  const HealthScoreComponent({
    required this.messageKey,
    required this.points,
    required this.maximumPoints,
  });

  final String messageKey;
  final double points;
  final double maximumPoints;

  factory HealthScoreComponent.fromJson(Map<String, dynamic> json) =>
      HealthScoreComponent(
        messageKey: '${json['message_key'] ?? ''}',
        points: _double(json['points']),
        maximumPoints: _double(json['maximum_points']),
      );

  Map<String, Object?> toJson() => {
    'message_key': messageKey,
    'points': points,
    'maximum_points': maximumPoints,
  };
}

class AccountSequenceEvaluation {
  const AccountSequenceEvaluation({
    required this.accountId,
    required this.isEligible,
    required this.isEligibleWithReducedRisk,
    required this.healthScore,
    required this.dailyLossUsagePercentage,
    required this.overallLossUsagePercentage,
    required this.remainingDailyLossRoom,
    required this.remainingOverallLossRoom,
    required this.remainingDailyLossPercentage,
    required this.remainingOverallLossPercentage,
    required this.currentLoss,
    required this.todayLoss,
    required this.healthStatus,
    required this.scoreComponents,
    required this.positiveFactors,
    required this.riskFactors,
    required this.exclusionReasons,
    required this.dataCompleteness,
  });

  final String accountId;
  final bool isEligible;
  final bool isEligibleWithReducedRisk;
  final double healthScore;
  final double dailyLossUsagePercentage;
  final double overallLossUsagePercentage;
  final double remainingDailyLossRoom;
  final double remainingOverallLossRoom;
  final double remainingDailyLossPercentage;
  final double remainingOverallLossPercentage;
  final double currentLoss;
  final double todayLoss;
  final AccountHealthStatus healthStatus;
  final List<HealthScoreComponent> scoreComponents;
  final List<SequenceReason> positiveFactors;
  final List<SequenceReason> riskFactors;
  final List<SequenceReason> exclusionReasons;
  final DataCompleteness dataCompleteness;

  factory AccountSequenceEvaluation.fromJson(Map<String, dynamic> json) =>
      AccountSequenceEvaluation(
        accountId: '${json['account_id'] ?? ''}',
        isEligible: _bool(json['is_eligible']),
        isEligibleWithReducedRisk: _bool(json['is_eligible_with_reduced_risk']),
        healthScore: _double(json['health_score']),
        dailyLossUsagePercentage: _double(json['daily_loss_usage_percentage']),
        overallLossUsagePercentage: _double(
          json['overall_loss_usage_percentage'],
        ),
        remainingDailyLossRoom: _double(json['remaining_daily_loss_room']),
        remainingOverallLossRoom: _double(json['remaining_overall_loss_room']),
        remainingDailyLossPercentage: _double(
          json['remaining_daily_loss_percentage'],
        ),
        remainingOverallLossPercentage: _double(
          json['remaining_overall_loss_percentage'],
        ),
        currentLoss: _double(json['current_loss']),
        todayLoss: _double(json['today_loss']),
        healthStatus: _enumValue(
          AccountHealthStatus.values,
          json['health_status'],
          AccountHealthStatus.paused,
        ),
        scoreComponents: _maps(
          json['score_components'],
        ).map(HealthScoreComponent.fromJson).toList(growable: false),
        positiveFactors: _maps(
          json['positive_factors'],
        ).map(SequenceReason.fromJson).toList(growable: false),
        riskFactors: _maps(
          json['risk_factors'],
        ).map(SequenceReason.fromJson).toList(growable: false),
        exclusionReasons: _maps(
          json['exclusion_reasons'],
        ).map(SequenceReason.fromJson).toList(growable: false),
        dataCompleteness: _enumValue(
          DataCompleteness.values,
          json['data_completeness'],
          DataCompleteness.insufficientAccountInformation,
        ),
      );

  Map<String, Object?> toJson() => {
    'account_id': accountId,
    'is_eligible': isEligible,
    'is_eligible_with_reduced_risk': isEligibleWithReducedRisk,
    'health_score': healthScore,
    'daily_loss_usage_percentage': dailyLossUsagePercentage,
    'overall_loss_usage_percentage': overallLossUsagePercentage,
    'remaining_daily_loss_room': remainingDailyLossRoom,
    'remaining_overall_loss_room': remainingOverallLossRoom,
    'remaining_daily_loss_percentage': remainingDailyLossPercentage,
    'remaining_overall_loss_percentage': remainingOverallLossPercentage,
    'current_loss': currentLoss,
    'today_loss': todayLoss,
    'health_status': healthStatus.name,
    'score_components': scoreComponents.map((item) => item.toJson()).toList(),
    'positive_factors': positiveFactors.map((item) => item.toJson()).toList(),
    'risk_factors': riskFactors.map((item) => item.toJson()).toList(),
    'exclusion_reasons': exclusionReasons.map((item) => item.toJson()).toList(),
    'data_completeness': dataCompleteness.name,
  };
}

class AccountSequenceStep {
  const AccountSequenceStep({
    required this.position,
    required this.accountId,
    this.alternativeAccountIds = const [],
    required this.role,
    required this.status,
    required this.healthScoreAtGeneration,
    required this.reasons,
    this.requiresReassessment = true,
  });

  final int position;
  final String accountId;
  final List<String> alternativeAccountIds;
  final SequenceAccountRole role;
  final SequenceStepStatus status;
  final double healthScoreAtGeneration;
  final List<SequenceReason> reasons;
  final bool requiresReassessment;

  AccountSequenceStep copyWith({
    SequenceStepStatus? status,
    SequenceAccountRole? role,
  }) => AccountSequenceStep(
    position: position,
    accountId: accountId,
    alternativeAccountIds: alternativeAccountIds,
    role: role ?? this.role,
    status: status ?? this.status,
    healthScoreAtGeneration: healthScoreAtGeneration,
    reasons: reasons,
    requiresReassessment: requiresReassessment,
  );

  factory AccountSequenceStep.fromJson(Map<String, dynamic> json) =>
      AccountSequenceStep(
        position: _int(json['position']),
        accountId: '${json['account_id'] ?? ''}',
        alternativeAccountIds: _strings(json['alternative_account_ids']),
        role: _enumValue(
          SequenceAccountRole.values,
          json['role'],
          SequenceAccountRole.unavailable,
        ),
        status: _enumValue(
          SequenceStepStatus.values,
          json['status'],
          SequenceStepStatus.pending,
        ),
        healthScoreAtGeneration: _double(json['health_score_at_generation']),
        reasons: _maps(
          json['reasons'],
        ).map(SequenceReason.fromJson).toList(growable: false),
        requiresReassessment: _bool(
          json['requires_reassessment'],
          fallback: true,
        ),
      );

  Map<String, Object?> toJson() => {
    'position': position,
    'account_id': accountId,
    'alternative_account_ids': alternativeAccountIds,
    'role': role.name,
    'status': status.name,
    'health_score_at_generation': healthScoreAtGeneration,
    'reasons': reasons.map((item) => item.toJson()).toList(),
    'requires_reassessment': requiresReassessment,
  };
}

class SequenceExclusion {
  const SequenceExclusion({required this.accountId, required this.reasons});

  final String accountId;
  final List<SequenceReason> reasons;

  factory SequenceExclusion.fromJson(Map<String, dynamic> json) =>
      SequenceExclusion(
        accountId: '${json['account_id'] ?? ''}',
        reasons: _maps(
          json['reasons'],
        ).map(SequenceReason.fromJson).toList(growable: false),
      );

  Map<String, Object?> toJson() => {
    'account_id': accountId,
    'reasons': reasons.map((item) => item.toJson()).toList(),
  };
}

class SequenceStopCondition {
  const SequenceStopCondition(this.messageKey);

  final String messageKey;

  factory SequenceStopCondition.fromJson(Map<String, dynamic> json) =>
      SequenceStopCondition('${json['message_key'] ?? ''}');

  Map<String, Object?> toJson() => {'message_key': messageKey};
}

class SequenceBranch {
  const SequenceBranch({
    required this.resultKey,
    required this.actions,
    this.nextCandidateId,
    this.stopsPortfolio = false,
  });

  final String resultKey;
  final List<SequenceReason> actions;
  final String? nextCandidateId;
  final bool stopsPortfolio;

  factory SequenceBranch.fromJson(Map<String, dynamic> json) => SequenceBranch(
    resultKey: '${json['result_key'] ?? ''}',
    actions: _maps(
      json['actions'],
    ).map(SequenceReason.fromJson).toList(growable: false),
    nextCandidateId: json['next_candidate_id']?.toString(),
    stopsPortfolio: _bool(json['stops_portfolio']),
  );

  Map<String, Object?> toJson() => {
    'result_key': resultKey,
    'actions': actions.map((item) => item.toJson()).toList(),
    'next_candidate_id': nextCandidateId,
    'stops_portfolio': stopsPortfolio,
  };
}

class AccountSequencePlan {
  const AccountSequencePlan({
    required this.id,
    required this.portfolioId,
    required this.requestedStartingAccountId,
    required this.selectedStartingAccountId,
    required this.mode,
    required this.steps,
    required this.exclusions,
    required this.reasons,
    required this.stopConditions,
    required this.evaluations,
    required this.branches,
    required this.dataCompleteness,
    required this.generatedAt,
    this.confirmedAt,
    this.isCustomised = false,
    this.startingAccountWasReplaced = false,
    this.portfolioStopped = false,
    this.updateReason,
  });

  final String id;
  final String portfolioId;
  final String requestedStartingAccountId;
  final String selectedStartingAccountId;
  final SequenceMode mode;
  final List<AccountSequenceStep> steps;
  final List<SequenceExclusion> exclusions;
  final List<SequenceReason> reasons;
  final List<SequenceStopCondition> stopConditions;
  final Map<String, AccountSequenceEvaluation> evaluations;
  final List<SequenceBranch> branches;
  final DataCompleteness dataCompleteness;
  final DateTime generatedAt;
  final DateTime? confirmedAt;
  final bool isCustomised;
  final bool startingAccountWasReplaced;
  final bool portfolioStopped;
  final SequenceReason? updateReason;

  AccountSequencePlan copyWith({
    List<AccountSequenceStep>? steps,
    DateTime? confirmedAt,
    bool clearConfirmation = false,
    SequenceReason? updateReason,
  }) => AccountSequencePlan(
    id: id,
    portfolioId: portfolioId,
    requestedStartingAccountId: requestedStartingAccountId,
    selectedStartingAccountId: selectedStartingAccountId,
    mode: mode,
    steps: steps ?? this.steps,
    exclusions: exclusions,
    reasons: reasons,
    stopConditions: stopConditions,
    evaluations: evaluations,
    branches: branches,
    dataCompleteness: dataCompleteness,
    generatedAt: generatedAt,
    confirmedAt: clearConfirmation ? null : confirmedAt ?? this.confirmedAt,
    isCustomised: isCustomised,
    startingAccountWasReplaced: startingAccountWasReplaced,
    portfolioStopped: portfolioStopped,
    updateReason: updateReason ?? this.updateReason,
  );

  factory AccountSequencePlan.fromJson(
    Map<String, dynamic> json,
  ) => AccountSequencePlan(
    id: '${json['id'] ?? ''}',
    portfolioId: '${json['portfolio_id'] ?? ''}',
    requestedStartingAccountId:
        '${json['requested_starting_account_id'] ?? ''}',
    selectedStartingAccountId: '${json['selected_starting_account_id'] ?? ''}',
    mode: _enumValue(
      SequenceMode.values,
      json['mode'],
      SequenceMode.healthiestNext,
    ),
    steps: _maps(
      json['steps'],
    ).map(AccountSequenceStep.fromJson).toList(growable: false),
    exclusions: _maps(
      json['exclusions'],
    ).map(SequenceExclusion.fromJson).toList(growable: false),
    reasons: _maps(
      json['reasons'],
    ).map(SequenceReason.fromJson).toList(growable: false),
    stopConditions: _maps(
      json['stop_conditions'],
    ).map(SequenceStopCondition.fromJson).toList(growable: false),
    evaluations: {
      for (final item in _maps(json['evaluations']))
        if ('${item['account_id'] ?? ''}'.isNotEmpty)
          '${item['account_id']}': AccountSequenceEvaluation.fromJson(item),
    },
    branches: _maps(
      json['branches'],
    ).map(SequenceBranch.fromJson).toList(growable: false),
    dataCompleteness: _enumValue(
      DataCompleteness.values,
      json['data_completeness'],
      DataCompleteness.insufficientAccountInformation,
    ),
    generatedAt: _date(json['generated_at']) ?? DateTime(2026),
    confirmedAt: _date(json['confirmed_at']),
    isCustomised: _bool(json['is_customised']),
    startingAccountWasReplaced: _bool(json['starting_account_was_replaced']),
    portfolioStopped: _bool(json['portfolio_stopped']),
    updateReason: json['update_reason'] is Map
        ? SequenceReason.fromJson(
            Map<String, dynamic>.from(json['update_reason'] as Map),
          )
        : null,
  );

  Map<String, Object?> toJson() => {
    'id': id,
    'portfolio_id': portfolioId,
    'requested_starting_account_id': requestedStartingAccountId,
    'selected_starting_account_id': selectedStartingAccountId,
    'mode': mode.name,
    'steps': steps.map((item) => item.toJson()).toList(),
    'exclusions': exclusions.map((item) => item.toJson()).toList(),
    'reasons': reasons.map((item) => item.toJson()).toList(),
    'stop_conditions': stopConditions.map((item) => item.toJson()).toList(),
    'evaluations': evaluations.values.map((item) => item.toJson()).toList(),
    'branches': branches.map((item) => item.toJson()).toList(),
    'data_completeness': dataCompleteness.name,
    'generated_at': generatedAt.toIso8601String(),
    'confirmed_at': confirmedAt?.toIso8601String(),
    'is_customised': isCustomised,
    'starting_account_was_replaced': startingAccountWasReplaced,
    'portfolio_stopped': portfolioStopped,
    'update_reason': updateReason?.toJson(),
  };
}

class AccountSequenceTemplate {
  const AccountSequenceTemplate({
    required this.id,
    required this.name,
    required this.mode,
    required this.startingAccountId,
    required this.accountOrder,
    required this.roles,
    required this.tiePreference,
    this.oneAccountOnly = false,
    required this.createdAt,
  });

  final String id;
  final String name;
  final SequenceMode mode;
  final String startingAccountId;
  final List<String> accountOrder;
  final Map<String, SequenceAccountRole> roles;
  final SequenceTiePreference tiePreference;
  final bool oneAccountOnly;
  final DateTime createdAt;

  factory AccountSequenceTemplate.fromJson(Map<String, dynamic> json) =>
      AccountSequenceTemplate(
        id: '${json['id'] ?? ''}',
        name: '${json['name'] ?? ''}',
        mode: _enumValue(
          SequenceMode.values,
          json['mode'],
          SequenceMode.healthiestNext,
        ),
        startingAccountId: '${json['starting_account_id'] ?? ''}',
        accountOrder: _strings(json['account_order']),
        roles: {
          for (final entry in Map<String, dynamic>.from(
            json['roles'] as Map? ?? const {},
          ).entries)
            entry.key: _enumValue(
              SequenceAccountRole.values,
              entry.value,
              SequenceAccountRole.unavailable,
            ),
        },
        tiePreference: _enumValue(
          SequenceTiePreference.values,
          json['tie_preference'],
          SequenceTiePreference.showAlternatives,
        ),
        oneAccountOnly: _bool(json['one_account_only']),
        createdAt: _date(json['created_at']) ?? DateTime(2026),
      );

  Map<String, Object?> toJson() => {
    'id': id,
    'name': name,
    'mode': mode.name,
    'starting_account_id': startingAccountId,
    'account_order': accountOrder,
    'roles': {for (final entry in roles.entries) entry.key: entry.value.name},
    'tie_preference': tiePreference.name,
    'one_account_only': oneAccountOnly,
    'created_at': createdAt.toIso8601String(),
  };
}

class AccountSequenceSettings {
  const AccountSequenceSettings({
    this.selectedStartingAccountId = '',
    this.mode = SequenceMode.healthiestNext,
    this.tiePreference = SequenceTiePreference.showAlternatives,
    this.tieThreshold = 5,
    this.minimumPlannedRisk = 25,
    this.customOrder = const [],
    this.roles = const {},
    this.skippedAccountReasons = const {},
    this.overrideReasons = const {},
    this.afterTpAction = SequenceAfterTpAction.moveToNext,
    this.afterSlAction = SequenceAfterSlAction.psychologyCheckThenNext,
    this.afterBreakevenAction = SequenceAfterBreakevenAction.recalculate,
    this.oneAccountOnly = false,
  });

  final String selectedStartingAccountId;
  final SequenceMode mode;
  final SequenceTiePreference tiePreference;
  final double tieThreshold;
  final double minimumPlannedRisk;
  final List<String> customOrder;
  final Map<String, SequenceAccountRole> roles;
  final Map<String, String> skippedAccountReasons;
  final Map<String, String> overrideReasons;
  final SequenceAfterTpAction afterTpAction;
  final SequenceAfterSlAction afterSlAction;
  final SequenceAfterBreakevenAction afterBreakevenAction;
  final bool oneAccountOnly;

  AccountSequenceSettings copyWith({
    String? selectedStartingAccountId,
    SequenceMode? mode,
    SequenceTiePreference? tiePreference,
    double? tieThreshold,
    double? minimumPlannedRisk,
    List<String>? customOrder,
    Map<String, SequenceAccountRole>? roles,
    Map<String, String>? skippedAccountReasons,
    Map<String, String>? overrideReasons,
    SequenceAfterTpAction? afterTpAction,
    SequenceAfterSlAction? afterSlAction,
    SequenceAfterBreakevenAction? afterBreakevenAction,
    bool? oneAccountOnly,
  }) => AccountSequenceSettings(
    selectedStartingAccountId:
        selectedStartingAccountId ?? this.selectedStartingAccountId,
    mode: mode ?? this.mode,
    tiePreference: tiePreference ?? this.tiePreference,
    tieThreshold: tieThreshold ?? this.tieThreshold,
    minimumPlannedRisk: minimumPlannedRisk ?? this.minimumPlannedRisk,
    customOrder: List.unmodifiable(customOrder ?? this.customOrder),
    roles: Map.unmodifiable(roles ?? this.roles),
    skippedAccountReasons: Map.unmodifiable(
      skippedAccountReasons ?? this.skippedAccountReasons,
    ),
    overrideReasons: Map.unmodifiable(overrideReasons ?? this.overrideReasons),
    afterTpAction: afterTpAction ?? this.afterTpAction,
    afterSlAction: afterSlAction ?? this.afterSlAction,
    afterBreakevenAction: afterBreakevenAction ?? this.afterBreakevenAction,
    oneAccountOnly: oneAccountOnly ?? this.oneAccountOnly,
  );

  factory AccountSequenceSettings.fromJson(Map<String, dynamic> json) =>
      AccountSequenceSettings(
        selectedStartingAccountId:
            '${json['selected_starting_account_id'] ?? ''}',
        mode: _enumValue(
          SequenceMode.values,
          json['mode'],
          SequenceMode.healthiestNext,
        ),
        tiePreference: _enumValue(
          SequenceTiePreference.values,
          json['tie_preference'],
          SequenceTiePreference.showAlternatives,
        ),
        tieThreshold: _double(json['tie_threshold'], 5).clamp(0, 25),
        minimumPlannedRisk: _double(
          json['minimum_planned_risk'],
          25,
        ).clamp(0, double.maxFinite),
        customOrder: _strings(json['custom_order']),
        roles: {
          for (final entry in Map<String, dynamic>.from(
            json['roles'] as Map? ?? const {},
          ).entries)
            entry.key: _enumValue(
              SequenceAccountRole.values,
              entry.value,
              SequenceAccountRole.unavailable,
            ),
        },
        skippedAccountReasons: Map<String, String>.from(
          json['skipped_account_reasons'] as Map? ?? const {},
        ),
        overrideReasons: Map<String, String>.from(
          json['override_reasons'] as Map? ?? const {},
        ),
        afterTpAction: _enumValue(
          SequenceAfterTpAction.values,
          json['after_tp_action'],
          SequenceAfterTpAction.moveToNext,
        ),
        afterSlAction: _enumValue(
          SequenceAfterSlAction.values,
          json['after_sl_action'],
          SequenceAfterSlAction.psychologyCheckThenNext,
        ),
        afterBreakevenAction: _enumValue(
          SequenceAfterBreakevenAction.values,
          json['after_breakeven_action'],
          SequenceAfterBreakevenAction.recalculate,
        ),
        oneAccountOnly: _bool(json['one_account_only']),
      );

  Map<String, Object?> toJson() => {
    'selected_starting_account_id': selectedStartingAccountId,
    'mode': mode.name,
    'tie_preference': tiePreference.name,
    'tie_threshold': tieThreshold,
    'minimum_planned_risk': minimumPlannedRisk,
    'custom_order': customOrder,
    'roles': {for (final entry in roles.entries) entry.key: entry.value.name},
    'skipped_account_reasons': skippedAccountReasons,
    'override_reasons': overrideReasons,
    'after_tp_action': afterTpAction.name,
    'after_sl_action': afterSlAction.name,
    'after_breakeven_action': afterBreakevenAction.name,
    'one_account_only': oneAccountOnly,
  };
}

class AccountSequenceWorkspaceState {
  const AccountSequenceWorkspaceState({
    this.schemaVersion = 1,
    this.settings = const AccountSequenceSettings(),
    this.plan,
    this.templates = const [],
    this.updatedAt,
  });

  final int schemaVersion;
  final AccountSequenceSettings settings;
  final AccountSequencePlan? plan;
  final List<AccountSequenceTemplate> templates;
  final DateTime? updatedAt;

  AccountSequenceWorkspaceState copyWith({
    AccountSequenceSettings? settings,
    AccountSequencePlan? plan,
    bool clearPlan = false,
    List<AccountSequenceTemplate>? templates,
    DateTime? updatedAt,
  }) => AccountSequenceWorkspaceState(
    schemaVersion: schemaVersion,
    settings: settings ?? this.settings,
    plan: clearPlan ? null : plan ?? this.plan,
    templates: List.unmodifiable(templates ?? this.templates),
    updatedAt: updatedAt ?? this.updatedAt,
  );

  factory AccountSequenceWorkspaceState.fromJson(Map<String, dynamic> json) {
    final schema = _int(json['schema_version'], 1);
    if (schema != 1) {
      throw const FormatException('Unsupported account sequence schema.');
    }
    return AccountSequenceWorkspaceState(
      settings: AccountSequenceSettings.fromJson(
        Map<String, dynamic>.from(json['settings'] as Map? ?? const {}),
      ),
      plan: json['plan'] is Map
          ? AccountSequencePlan.fromJson(
              Map<String, dynamic>.from(json['plan'] as Map),
            )
          : null,
      templates: _maps(
        json['templates'],
      ).map(AccountSequenceTemplate.fromJson).toList(growable: false),
      updatedAt: _date(json['updated_at']),
    );
  }

  Map<String, Object?> toJson() => {
    'schema_version': schemaVersion,
    'settings': settings.toJson(),
    'plan': plan?.toJson(),
    'templates': templates.map((item) => item.toJson()).toList(),
    'updated_at': updatedAt?.toIso8601String(),
  };
}

class SequenceValidationResult {
  const SequenceValidationResult({
    this.blockingReasons = const [],
    this.warnings = const [],
  });

  final List<SequenceReason> blockingReasons;
  final List<SequenceReason> warnings;

  bool get canSave => blockingReasons.isEmpty;
  bool get requiresOverride => warnings.isNotEmpty;
}

T _enumValue<T extends Enum>(List<T> values, Object? raw, T fallback) {
  final name = '$raw';
  return values.where((item) => item.name == name).firstOrNull ?? fallback;
}

double _double(Object? value, [double fallback = 0]) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? fallback;

int _int(Object? value, [int fallback = 0]) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? fallback;

bool _bool(Object? value, {bool fallback = false}) =>
    value is bool ? value : fallback;

DateTime? _date(Object? value) => value == null
    ? null
    : value is DateTime
    ? value
    : DateTime.tryParse('$value');

List<Map<String, dynamic>> _maps(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList(growable: false)
    : const [];

List<String> _strings(Object? value) => value is List
    ? value.map((item) => '$item').toList(growable: false)
    : const [];

extension<T> on Iterable<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
