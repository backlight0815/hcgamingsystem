import 'dart:math' as math;

import 'account_daily_trade_limit_service.dart';
import 'account_health_decision_service.dart';
import 'account_health_score_service.dart';
import 'account_sequence_models.dart';
import 'prop_firm_calculation_service.dart';
import 'prop_firm_models.dart';

class AccountSequenceService {
  const AccountSequenceService({
    this.health = const AccountHealthService(),
    this.healthDecisions = const PortfolioHealthDecisionService(),
    this.healthScores = const AccountHealthScoreService(),
    this.calculations = const PropFirmCalculationService(),
    this.dailyTradeLimits = const AccountDailyTradeLimitService(),
  });

  final AccountHealthService health;
  final PortfolioHealthDecisionService healthDecisions;
  final AccountHealthScoreService healthScores;
  final PropFirmCalculationService calculations;
  final AccountDailyTradeLimitService dailyTradeLimits;

  AccountSequencePlan generateSequence({
    required PropFirmPortfolio portfolio,
    required AccountSequenceSettings settings,
    required DateTime now,
    AccountSequencePlan? previousPlan,
  }) {
    final portfolioRecommendation = healthDecisions.recommend(
      portfolio,
      now: now,
    );
    final portfolioStopped =
        calculations.hasReachedGlobalStop(portfolio, now) ||
        {
          PortfolioRecommendationType.stopPortfolio,
          PortfolioRecommendationType.pauseAndReview,
        }.contains(portfolioRecommendation.type);
    final evaluations = <String, AccountSequenceEvaluation>{
      for (final account in portfolio.accounts)
        account.id: evaluateAccount(
          portfolio: portfolio,
          account: account,
          settings: settings,
          now: now,
          portfolioStopped: portfolioStopped,
        ),
    };
    final exclusions = evaluations.values
        .where((item) => !item.isEligible)
        .map(
          (item) => SequenceExclusion(
            accountId: item.accountId,
            reasons: item.exclusionReasons,
          ),
        )
        .toList(growable: false);
    final stopConditions = _stopConditions();
    final requested = settings.selectedStartingAccountId;
    final eligible = portfolio.activeAccounts
        .where((account) => evaluations[account.id]!.isEligible)
        .toList(growable: false);
    final systemRanked = _rank(
      accounts: eligible,
      evaluations: evaluations,
      portfolio: portfolio,
      settings: settings.copyWith(mode: SequenceMode.healthiestNext),
      now: now,
    );

    if (portfolioStopped || systemRanked.isEmpty) {
      final generated = AccountSequencePlan(
        id: _planId(portfolio.id, now),
        portfolioId: portfolio.id,
        requestedStartingAccountId: requested,
        selectedStartingAccountId: '',
        mode: settings.mode,
        steps: const [],
        exclusions: exclusions,
        reasons: [
          SequenceReason(
            portfolioStopped
                ? 'sequence.reason.portfolioStopped'
                : 'sequence.reason.noEligibleAccount',
          ),
        ],
        stopConditions: stopConditions,
        evaluations: Map.unmodifiable(evaluations),
        branches: const [],
        dataCompleteness: _portfolioCompleteness(evaluations.values),
        generatedAt: now,
        isCustomised: settings.mode == SequenceMode.custom,
        startingAccountWasReplaced: requested.isNotEmpty,
        portfolioStopped: portfolioStopped,
      );
      return generated.copyWith(
        updateReason: _updateReason(previousPlan, generated),
      );
    }

    final requestedEvaluation = evaluations[requested];
    final requestedIsEligible =
        requested.isNotEmpty && requestedEvaluation?.isEligible == true;
    final starting = requestedIsEligible
        ? portfolio.accounts.firstWhere((item) => item.id == requested)
        : systemRanked.first;
    final replaced = requested.isNotEmpty && starting.id != requested;
    final remaining = settings.oneAccountOnly
        ? const <PropFirmAccount>[]
        : eligible
              .where((account) => account.id != starting.id)
              .toList(growable: false);
    final ranked = _rank(
      accounts: remaining,
      evaluations: evaluations,
      portfolio: portfolio,
      settings: settings,
      now: now,
    );
    final steps = <AccountSequenceStep>[
      AccountSequenceStep(
        position: 1,
        accountId: starting.id,
        role: settings.roles[starting.id] ?? SequenceAccountRole.starting,
        status: SequenceStepStatus.pending,
        healthScoreAtGeneration: evaluations[starting.id]!.healthScore,
        reasons: [
          SequenceReason(
            replaced
                ? 'sequence.reason.alternativeStartingAccount'
                : requested.isEmpty
                ? 'sequence.reason.systemStartingAccount'
                : 'sequence.reason.userStartingAccount',
            {'account': starting.name},
          ),
          ...evaluations[starting.id]!.positiveFactors.take(2),
          if (evaluations[starting.id]!.isEligibleWithReducedRisk)
            SequenceReason('sequence.reason.reducedRiskStarting', {
              'account': starting.name,
              'percent': evaluations[starting.id]!.dailyLossUsagePercentage
                  .toStringAsFixed(0),
            }),
        ],
      ),
    ];
    _appendRankedSteps(
      steps: steps,
      ranked: ranked,
      evaluations: evaluations,
      settings: settings,
    );
    final nextCandidate = steps.length > 1 ? steps[1].accountId : null;
    final generated = AccountSequencePlan(
      id: _planId(portfolio.id, now),
      portfolioId: portfolio.id,
      requestedStartingAccountId: requested,
      selectedStartingAccountId: starting.id,
      mode: settings.mode,
      steps: List.unmodifiable(steps),
      exclusions: exclusions,
      reasons: [
        if (replaced)
          SequenceReason('sequence.reason.requestedUnavailable', {
            'requested': _accountName(portfolio, requested),
            'alternative': starting.name,
          }),
        SequenceReason('sequence.reason.healthiestRemaining', {
          'account': steps.length > 1
              ? _accountName(portfolio, steps[1].accountId)
              : starting.name,
        }),
        if (portfolio.accounts.map((item) => item.currency).toSet().length > 1)
          const SequenceReason('sequence.reason.normalizedCurrencyComparison'),
      ],
      stopConditions: stopConditions,
      evaluations: Map.unmodifiable(evaluations),
      branches: _branches(settings, nextCandidate),
      dataCompleteness: _portfolioCompleteness(evaluations.values),
      generatedAt: now,
      isCustomised: settings.mode == SequenceMode.custom,
      startingAccountWasReplaced: replaced,
      portfolioStopped: false,
    );
    return generated.copyWith(
      updateReason: _updateReason(previousPlan, generated),
    );
  }

  AccountSequenceEvaluation evaluateAccount({
    required PropFirmPortfolio portfolio,
    required PropFirmAccount account,
    required AccountSequenceSettings settings,
    required DateTime now,
    bool? portfolioStopped,
  }) {
    final assessment = health.assess(account, now: now);
    final loss = assessment.lossLimits;
    final simplifiedScore = healthScores.calculate(account, now: now);
    final scoreComponents = [
      for (final factor in simplifiedScore.factors)
        HealthScoreComponent(
          messageKey: factor.key,
          points: factor.points,
          maximumPoints: factor.maximumPoints,
        ),
    ];
    final healthScore = scoreComponents
        .fold<double>(0, (total, item) => total + item.points)
        .clamp(0, 100)
        .toDouble();
    final exclusions = <SequenceReason>[];
    final risks = <SequenceReason>[];
    final positives = <SequenceReason>[];
    final hardPortfolioStop =
        portfolioStopped ?? calculations.hasReachedGlobalStop(portfolio, now);
    final criticalDataMissing = _criticalDataMissing(account);
    final dailyTradeLimit = dailyTradeLimits.evaluate(
      portfolio,
      account,
      now: now,
    );
    final inCooldown =
        {
          AccountStatus.coolingAfterWin,
          AccountStatus.coolingAfterLoss,
        }.contains(account.status) &&
        (account.cooldownUntil == null || now.isBefore(account.cooldownUntil!));
    final payoutProtected =
        account.situation.protectUntilPayout ||
        {
          AccountHealthStatus.payoutProtection,
          AccountHealthStatus.payoutEligible,
          AccountHealthStatus.waitingForPayoutDate,
        }.contains(assessment.status) ||
        {
          AccountStatus.payoutProtection,
          AccountStatus.payoutReady,
          AccountStatus.payoutRequested,
        }.contains(account.status);
    final minimumRisk = math.min(
      math.max(0, account.maxRiskPerTrade),
      math.max(0, settings.minimumPlannedRisk),
    );

    if (hardPortfolioStop) {
      exclusions.add(const SequenceReason('sequence.exclusion.portfolioStop'));
    }
    if (account.status == AccountStatus.breached ||
        assessment.status == AccountHealthStatus.breached) {
      exclusions.add(const SequenceReason('sequence.exclusion.breached'));
    }
    if (assessment.status == AccountHealthStatus.critical) {
      exclusions.add(const SequenceReason('sequence.exclusion.critical'));
    }
    if (account.isEvaluationAccount &&
        account.profitTarget > 0 &&
        account.currentProfit >= account.profitTarget) {
      exclusions.add(
        const SequenceReason('sequence.exclusion.evaluationPassed'),
      );
    }
    if (!account.isAvailableToday) {
      exclusions.add(
        const SequenceReason('sequence.exclusion.manualUnavailable'),
      );
    }
    if (account.isManuallyExcluded) {
      exclusions.add(const SequenceReason('sequence.exclusion.manualExcluded'));
    }
    if (account.isArchived ||
        assessment.status == AccountHealthStatus.archived) {
      exclusions.add(const SequenceReason('sequence.exclusion.archived'));
    }
    if (account.status == AccountStatus.dailyLocked ||
        assessment.status == AccountHealthStatus.dailyLocked) {
      exclusions.add(const SequenceReason('sequence.exclusion.dailyLocked'));
    }
    if (account.status == AccountStatus.portfolioLocked) {
      exclusions.add(
        const SequenceReason('sequence.exclusion.accountPortfolioLocked'),
      );
    }
    if (account.status == AccountStatus.paused ||
        assessment.status == AccountHealthStatus.paused) {
      exclusions.add(const SequenceReason('sequence.exclusion.paused'));
    }
    if (inCooldown) {
      exclusions.add(const SequenceReason('sequence.exclusion.cooldown'));
    }
    if (criticalDataMissing.isNotEmpty) {
      exclusions.addAll(criticalDataMissing);
    }
    if (dailyTradeLimit.hasReachedLimit) {
      exclusions.add(
        SequenceReason('sequence.exclusion.tradeLimitReached', {
          'current': dailyTradeLimit.currentTrades,
          'limit': dailyTradeLimit.limit,
        }),
      );
    }
    if (loss.dailyLossRemaining < minimumRisk) {
      exclusions.add(
        SequenceReason('sequence.exclusion.dailyRoomTooSmall', {
          'amount': loss.dailyLossRemaining.toStringAsFixed(2),
        }),
      );
    }
    if (loss.maximumLossRemaining < minimumRisk) {
      exclusions.add(
        SequenceReason('sequence.exclusion.overallRoomTooSmall', {
          'amount': loss.maximumLossRemaining.toStringAsFixed(2),
        }),
      );
    }
    if (portfolio.openPlans.any((plan) => plan.accountId != account.id) &&
        !portfolio.riskPolicy.simultaneousTradingAllowed) {
      exclusions.add(
        const SequenceReason('sequence.exclusion.openPortfolioExposure'),
      );
    }
    if (settings.skippedAccountReasons.containsKey(account.id)) {
      exclusions.add(
        SequenceReason('sequence.exclusion.skipped', {
          'reason': settings.skippedAccountReasons[account.id]!,
        }),
      );
    }
    if (settings.roles[account.id] == SequenceAccountRole.unavailable) {
      exclusions.add(
        const SequenceReason('sequence.exclusion.manualUnavailable'),
      );
    }
    if (account.isManuallyProtected ||
        payoutProtected ||
        settings.roles[account.id] == SequenceAccountRole.payoutProtected) {
      exclusions.add(
        const SequenceReason('sequence.exclusion.payoutProtected'),
      );
    }

    final overrideReason = settings.overrideReasons[account.id]?.trim() ?? '';
    if (overrideReason.length >= 5 &&
        exclusions.isNotEmpty &&
        exclusions.every(
          (reason) => reason.messageKey == 'sequence.exclusion.payoutProtected',
        )) {
      exclusions.clear();
      risks.add(
        SequenceReason('sequence.risk.payoutOverride', {
          'reason': overrideReason,
        }),
      );
    }

    if (loss.dailyUsagePercent <= 30) {
      positives.add(
        SequenceReason('sequence.positive.dailyBuffer', {
          'percent': (100 - loss.dailyUsagePercent).toStringAsFixed(0),
        }),
      );
    } else {
      risks.add(
        SequenceReason('sequence.risk.dailyUsage', {
          'percent': loss.dailyUsagePercent.toStringAsFixed(0),
        }),
      );
    }
    if (loss.maximumUsagePercent <= 30) {
      positives.add(
        SequenceReason('sequence.positive.overallBuffer', {
          'percent': (100 - loss.maximumUsagePercent).toStringAsFixed(0),
        }),
      );
    } else {
      risks.add(
        SequenceReason('sequence.risk.overallUsage', {
          'percent': loss.maximumUsagePercent.toStringAsFixed(0),
        }),
      );
    }
    if (account.consecutiveLosses > 0) {
      risks.add(
        SequenceReason('sequence.risk.lossStreak', {
          'count': account.consecutiveLosses,
        }),
      );
    }
    if (account.situation.emotionallyAffected) {
      risks.add(const SequenceReason('sequence.risk.emotionalState'));
    }
    if (assessment.missingData.isNotEmpty) {
      risks.add(
        SequenceReason('sequence.risk.incompleteData', {
          'count': assessment.missingData.length,
        }),
      );
    }
    if (assessment.status == AccountHealthStatus.recovery ||
        account.status == AccountStatus.recovery) {
      risks.add(const SequenceReason('sequence.risk.recoveryAccount'));
    }
    if (!payoutProtected && assessment.payout.tradingDaysRemaining > 0) {
      positives.add(
        SequenceReason('sequence.positive.requirementDays', {
          'count': assessment.payout.tradingDaysRemaining,
        }),
      );
    }

    final completeness = criticalDataMissing.isNotEmpty
        ? DataCompleteness.insufficientAccountInformation
        : assessment.missingData.isEmpty
        ? DataCompleteness.completeAccountInformation
        : DataCompleteness.partiallyCompleteAccountInformation;
    final eligible = exclusions.isEmpty;
    final reduced =
        eligible &&
        (healthScore < 80 ||
            {
              AccountHealthStatus.caution,
              AccountHealthStatus.highRisk,
              AccountHealthStatus.recovery,
              AccountHealthStatus.payoutProgress,
            }.contains(assessment.status) ||
            assessment.maximumSuggestedRisk < account.maxRiskPerTrade);
    return AccountSequenceEvaluation(
      accountId: account.id,
      isEligible: eligible,
      isEligibleWithReducedRisk: reduced,
      healthScore: _oneDecimal(healthScore),
      dailyLossUsagePercentage: _oneDecimal(loss.dailyUsagePercent),
      overallLossUsagePercentage: _oneDecimal(loss.maximumUsagePercent),
      remainingDailyLossRoom: loss.dailyLossRemaining,
      remainingOverallLossRoom: loss.maximumLossRemaining,
      remainingDailyLossPercentage: _oneDecimal(
        (100 - loss.dailyUsagePercent).clamp(0, 100).toDouble(),
      ),
      remainingOverallLossPercentage: _oneDecimal(
        (100 - loss.maximumUsagePercent).clamp(0, 100).toDouble(),
      ),
      currentLoss: math.max(
        0,
        account.startingBalance - account.currentBalance,
      ),
      todayLoss: math.max(
        math.max(0, -account.situation.todayRealisedProfitLoss),
        account.situation.lossAlreadyRecordedToday,
      ),
      healthStatus: assessment.status,
      scoreComponents: List.unmodifiable(scoreComponents),
      positiveFactors: List.unmodifiable(positives),
      riskFactors: List.unmodifiable(risks),
      exclusionReasons: List.unmodifiable(_deduplicate(exclusions)),
      dataCompleteness: completeness,
    );
  }

  SequenceValidationResult validateCustomOrder({
    required PropFirmPortfolio portfolio,
    required AccountSequenceSettings settings,
    required List<String> accountIds,
    required DateTime now,
  }) {
    final blocking = <SequenceReason>[];
    final warnings = <SequenceReason>[];
    if (accountIds.isEmpty || accountIds.toSet().length != accountIds.length) {
      blocking.add(
        const SequenceReason('sequence.validation.uniqueAccountsRequired'),
      );
      return SequenceValidationResult(blockingReasons: blocking);
    }
    final evaluations = {
      for (final account in portfolio.accounts)
        account.id: evaluateAccount(
          portfolio: portfolio,
          account: account,
          settings: settings,
          now: now,
        ),
    };
    for (final id in accountIds) {
      final account = portfolio.accounts
          .where((item) => item.id == id)
          .firstOrNull;
      final evaluation = evaluations[id];
      if (account == null || evaluation == null) {
        blocking.add(
          const SequenceReason('sequence.validation.unknownAccount'),
        );
        continue;
      }
      final nonOverrideable = evaluation.exclusionReasons.where(
        (reason) => reason.messageKey != 'sequence.exclusion.payoutProtected',
      );
      if (nonOverrideable.isNotEmpty) blocking.addAll(nonOverrideable);
      if (evaluation.exclusionReasons.any(
        (reason) => reason.messageKey == 'sequence.exclusion.payoutProtected',
      )) {
        warnings.add(
          SequenceReason('sequence.validation.payoutProtectedIncluded', {
            'account': account.name,
          }),
        );
      }
    }
    for (var index = 0; index < accountIds.length - 1; index++) {
      final current = evaluations[accountIds[index]];
      final later = evaluations[accountIds[index + 1]];
      if (current == null || later == null) continue;
      final currentAccount = portfolio.accounts.firstWhere(
        (item) => item.id == current.accountId,
      );
      final laterAccount = portfolio.accounts.firstWhere(
        (item) => item.id == later.accountId,
      );
      if (later.healthScore - current.healthScore > settings.tieThreshold) {
        warnings.add(
          SequenceReason('sequence.validation.higherRiskBeforeHealthy', {
            'higherRisk': currentAccount.name,
            'healthier': laterAccount.name,
          }),
        );
      }
      if (current.healthStatus == AccountHealthStatus.recovery &&
          later.healthStatus != AccountHealthStatus.recovery) {
        warnings.add(
          SequenceReason('sequence.validation.recoveryBeforeHealthy', {
            'recovery': currentAccount.name,
            'healthier': laterAccount.name,
          }),
        );
      }
    }
    return SequenceValidationResult(
      blockingReasons: _deduplicate(blocking),
      warnings: _deduplicate(warnings),
    );
  }

  List<SequenceReason> _criticalDataMissing(PropFirmAccount account) => [
    if (!account.accountSize.isFinite || account.accountSize <= 0)
      const SequenceReason('sequence.exclusion.invalidAccountSize'),
    if (!account.startingBalance.isFinite || account.startingBalance <= 0)
      const SequenceReason('sequence.exclusion.invalidStartingBalance'),
    if (!account.currentBalance.isFinite || account.currentBalance <= 0)
      const SequenceReason('sequence.exclusion.invalidCurrentBalance'),
    if ((!account.dailyLossLimit.isFinite || account.dailyLossLimit <= 0) &&
        account.situation.dailyLossAmount <= 0)
      const SequenceReason('sequence.exclusion.missingDailyLimit'),
    if ((!account.maximumLossLimit.isFinite || account.maximumLossLimit <= 0) &&
        account.situation.maximumLossAmount <= 0)
      const SequenceReason('sequence.exclusion.missingMaximumLimit'),
    if (!account.maxRiskPerTrade.isFinite || account.maxRiskPerTrade <= 0)
      const SequenceReason('sequence.exclusion.invalidRisk'),
    if (account.drawdownType == DrawdownType.equityBased &&
        account.manuallyEnteredEquity == null)
      const SequenceReason('sequence.exclusion.missingRequiredEquity'),
  ];

  List<PropFirmAccount> _rank({
    required List<PropFirmAccount> accounts,
    required Map<String, AccountSequenceEvaluation> evaluations,
    required PropFirmPortfolio portfolio,
    required AccountSequenceSettings settings,
    required DateTime now,
  }) {
    final ranked = [...accounts];
    final order = settings.customOrder.isNotEmpty
        ? settings.customOrder
        : portfolio.accounts.map((item) => item.id).toList(growable: false);
    int orderIndex(String id) {
      final index = order.indexOf(id);
      return index < 0 ? 9999 : index;
    }

    int healthiest(PropFirmAccount left, PropFirmAccount right) {
      final a = evaluations[left.id]!;
      final b = evaluations[right.id]!;
      final health = b.healthScore.compareTo(a.healthScore);
      if (health != 0) return health;
      final dailyPercent = b.remainingDailyLossPercentage.compareTo(
        a.remainingDailyLossPercentage,
      );
      if (dailyPercent != 0) return dailyPercent;
      final overallPercent = b.remainingOverallLossPercentage.compareTo(
        a.remainingOverallLossPercentage,
      );
      if (overallPercent != 0) return overallPercent;
      return _leastRecentlyUsed(left, right, now);
    }

    int priority(PropFirmAccount account) => switch (account.priority) {
      AccountPriority.high => 0,
      AccountPriority.standard => 1,
      AccountPriority.low => 2,
    };

    int roleRisk(PropFirmAccount account) =>
        switch (settings.roles[account.id]) {
          SequenceAccountRole.emergencyOnly => 2,
          SequenceAccountRole.recovery => 1,
          _ => 0,
        };

    int tieBreak(PropFirmAccount left, PropFirmAccount right) {
      final a = evaluations[left.id]!;
      final b = evaluations[right.id]!;
      final comparison = switch (settings.tiePreference) {
        SequenceTiePreference.leastRecentlyUsed => _leastRecentlyUsed(
          left,
          right,
          now,
        ),
        SequenceTiePreference.largestDailyLossBuffer =>
          b.remainingDailyLossPercentage.compareTo(
            a.remainingDailyLossPercentage,
          ),
        SequenceTiePreference.largestOverallLossBuffer =>
          b.remainingOverallLossPercentage.compareTo(
            a.remainingOverallLossPercentage,
          ),
        SequenceTiePreference.customOrder => orderIndex(
          left.id,
        ).compareTo(orderIndex(right.id)),
        SequenceTiePreference.showAlternatives => 0,
      };
      if (comparison != 0) return comparison;
      final accountPriority = priority(left).compareTo(priority(right));
      if (accountPriority != 0) return accountPriority;
      final trades = dailyTradeLimits
          .evaluate(portfolio, left, now: now)
          .currentTrades
          .compareTo(
            dailyTradeLimits.evaluate(portfolio, right, now: now).currentTrades,
          );
      if (trades != 0) return trades;
      return left.id.compareTo(right.id);
    }

    ranked.sort((left, right) {
      if (settings.mode == SequenceMode.fixedRotation ||
          settings.mode == SequenceMode.custom) {
        return orderIndex(left.id).compareTo(orderIndex(right.id));
      }
      final role = roleRisk(left).compareTo(roleRisk(right));
      if (role != 0) return role;
      if (settings.mode == SequenceMode.recoveryLast) {
        final leftRecovery =
            left.status == AccountStatus.recovery ||
            evaluations[left.id]!.healthStatus == AccountHealthStatus.recovery;
        final rightRecovery =
            right.status == AccountStatus.recovery ||
            evaluations[right.id]!.healthStatus == AccountHealthStatus.recovery;
        if (leftRecovery != rightRecovery) return leftRecovery ? 1 : -1;
      }
      if (settings.mode == SequenceMode.leastRecentlyUsed) {
        final leftBand = _scoreBand(evaluations[left.id]!.healthScore);
        final rightBand = _scoreBand(evaluations[right.id]!.healthScore);
        if (leftBand != rightBand) return rightBand.compareTo(leftBand);
        final recent = _leastRecentlyUsed(left, right, now);
        if (recent != 0) return recent;
      }
      final leftScore = evaluations[left.id]!.healthScore;
      final rightScore = evaluations[right.id]!.healthScore;
      if ((leftScore - rightScore).abs() <= settings.tieThreshold) {
        final tied = tieBreak(left, right);
        if (tied != 0) return tied;
      }
      final health = healthiest(left, right);
      if (health != 0) return health;
      return tieBreak(left, right);
    });
    return ranked;
  }

  void _appendRankedSteps({
    required List<AccountSequenceStep> steps,
    required List<PropFirmAccount> ranked,
    required Map<String, AccountSequenceEvaluation> evaluations,
    required AccountSequenceSettings settings,
  }) {
    var index = 0;
    while (index < ranked.length) {
      final account = ranked[index];
      final evaluation = evaluations[account.id]!;
      final alternatives = <String>[];
      if (settings.tiePreference == SequenceTiePreference.showAlternatives) {
        for (
          var candidateIndex = index + 1;
          candidateIndex < ranked.length;
          candidateIndex++
        ) {
          final candidate = ranked[candidateIndex];
          final difference =
              (evaluation.healthScore - evaluations[candidate.id]!.healthScore)
                  .abs();
          if (difference > settings.tieThreshold) break;
          alternatives.add(candidate.id);
        }
      }
      steps.add(
        AccountSequenceStep(
          position: steps.length + 1,
          accountId: account.id,
          alternativeAccountIds: alternatives,
          role:
              settings.roles[account.id] ??
              (steps.length == 1
                  ? SequenceAccountRole.firstBackup
                  : steps.length == 2
                  ? SequenceAccountRole.secondBackup
                  : evaluation.healthStatus == AccountHealthStatus.recovery
                  ? SequenceAccountRole.recovery
                  : SequenceAccountRole.emergencyOnly),
          status: SequenceStepStatus.pending,
          healthScoreAtGeneration: evaluation.healthScore,
          reasons: [
            SequenceReason('sequence.reason.rankedByHealth', {
              'score': evaluation.healthScore.toStringAsFixed(0),
              'daily': evaluation.dailyLossUsagePercentage.toStringAsFixed(0),
              'overall': evaluation.overallLossUsagePercentage.toStringAsFixed(
                0,
              ),
            }),
            ...evaluation.positiveFactors.take(2),
            if (alternatives.isNotEmpty)
              const SequenceReason('sequence.reason.similarHealthAlternative'),
            if (evaluation.healthStatus == AccountHealthStatus.recovery)
              const SequenceReason('sequence.reason.recoveryPlacedLast'),
          ],
        ),
      );
      index += alternatives.length + 1;
    }
  }

  List<SequenceBranch> _branches(
    AccountSequenceSettings settings,
    String? nextCandidateId,
  ) => [
    SequenceBranch(
      resultKey: 'sequence.branch.tp',
      actions: [
        SequenceReason(_afterTpKey(settings.afterTpAction)),
        if (nextCandidateId != null)
          const SequenceReason('sequence.branch.reassessNext'),
      ],
      nextCandidateId: nextCandidateId,
      // The action is conditional on the existing planner's daily target;
      // choosing it does not turn every take-profit into a portfolio stop.
      stopsPortfolio: false,
    ),
    SequenceBranch(
      resultKey: 'sequence.branch.sl',
      actions: [
        SequenceReason(_afterSlKey(settings.afterSlAction)),
        const SequenceReason('sequence.branch.reassessPortfolio'),
        if (nextCandidateId != null)
          const SequenceReason('sequence.branch.nextOnlyIfAllowed'),
      ],
      nextCandidateId: nextCandidateId,
      stopsPortfolio:
          settings.afterSlAction == SequenceAfterSlAction.stopPortfolio,
    ),
    SequenceBranch(
      resultKey: 'sequence.branch.breakeven',
      actions: [
        SequenceReason(_afterBreakevenKey(settings.afterBreakevenAction)),
      ],
      nextCandidateId: nextCandidateId,
    ),
    SequenceBranch(
      resultKey: 'sequence.branch.dailyTarget',
      actions: const [
        SequenceReason('sequence.branch.protectCurrent'),
        SequenceReason('sequence.branch.reassessNext'),
      ],
      nextCandidateId: nextCandidateId,
    ),
    const SequenceBranch(
      resultKey: 'sequence.branch.portfolioStop',
      actions: [SequenceReason('sequence.branch.stopAll')],
      stopsPortfolio: true,
    ),
  ];

  List<SequenceStopCondition> _stopConditions() => const [
    SequenceStopCondition('sequence.stop.portfolioDailyLoss'),
    SequenceStopCondition('sequence.stop.consecutiveLosses'),
    SequenceStopCondition('sequence.stop.emotionalRisk'),
    SequenceStopCondition('sequence.stop.repeatedInvalidSetup'),
    SequenceStopCondition('sequence.stop.noEligibleAccount'),
    SequenceStopCondition('sequence.stop.payoutProtectedOnly'),
    SequenceStopCondition('sequence.stop.plannedRiskTooLarge'),
    SequenceStopCondition('sequence.stop.manualSessionStop'),
  ];

  DataCompleteness _portfolioCompleteness(
    Iterable<AccountSequenceEvaluation> evaluations,
  ) {
    if (evaluations.any(
      (item) =>
          item.dataCompleteness ==
          DataCompleteness.insufficientAccountInformation,
    )) {
      return DataCompleteness.insufficientAccountInformation;
    }
    if (evaluations.any(
      (item) =>
          item.dataCompleteness ==
          DataCompleteness.partiallyCompleteAccountInformation,
    )) {
      return DataCompleteness.partiallyCompleteAccountInformation;
    }
    return DataCompleteness.completeAccountInformation;
  }

  SequenceReason _updateReason(
    AccountSequencePlan? previous,
    AccountSequencePlan current,
  ) {
    if (previous == null) {
      return const SequenceReason('sequence.update.initialGeneration');
    }
    final oldOrder = previous.steps.map((item) => item.accountId).toList();
    final newOrder = current.steps.map((item) => item.accountId).toList();
    if (_sameList(oldOrder, newOrder)) {
      return const SequenceReason('sequence.update.recheckedNoOrderChange');
    }
    final moved = newOrder.where((id) => oldOrder.contains(id)).firstOrNull;
    if (moved != null) {
      final before = previous.evaluations[moved];
      final after = current.evaluations[moved];
      return SequenceReason('sequence.update.healthChanged', {
        'account': moved,
        'before': before?.dailyLossUsagePercentage.toStringAsFixed(0) ?? '0',
        'after': after?.dailyLossUsagePercentage.toStringAsFixed(0) ?? '0',
      });
    }
    return const SequenceReason('sequence.update.eligibilityChanged');
  }

  int _leastRecentlyUsed(
    PropFirmAccount left,
    PropFirmAccount right,
    DateTime now,
  ) {
    final leftAge = left.lastTradeAt == null
        ? const Duration(days: 36500)
        : now.difference(left.lastTradeAt!);
    final rightAge = right.lastTradeAt == null
        ? const Duration(days: 36500)
        : now.difference(right.lastTradeAt!);
    return rightAge.compareTo(leftAge);
  }

  int _scoreBand(double score) => score >= 80
      ? 4
      : score >= 60
      ? 3
      : score >= 40
      ? 2
      : score >= 20
      ? 1
      : 0;

  String _afterTpKey(SequenceAfterTpAction action) => switch (action) {
    SequenceAfterTpAction.moveToNext => 'sequence.afterTp.moveToNext',
    SequenceAfterTpAction.keepAfterCooldown =>
      'sequence.afterTp.keepAfterCooldown',
    SequenceAfterTpAction.protectForDay => 'sequence.afterTp.protectForDay',
    SequenceAfterTpAction.stopAfterDailyTarget =>
      'sequence.afterTp.stopAfterDailyTarget',
    SequenceAfterTpAction.recalculate => 'sequence.afterTp.recalculate',
  };

  String _afterSlKey(SequenceAfterSlAction action) => switch (action) {
    SequenceAfterSlAction.lockForDay => 'sequence.afterSl.lockForDay',
    SequenceAfterSlAction.startCooldown => 'sequence.afterSl.startCooldown',
    SequenceAfterSlAction.psychologyCheckThenNext =>
      'sequence.afterSl.psychologyCheckThenNext',
    SequenceAfterSlAction.stopPortfolio => 'sequence.afterSl.stopPortfolio',
    SequenceAfterSlAction.recalculateRemainingRisk =>
      'sequence.afterSl.recalculateRemainingRisk',
  };

  String _afterBreakevenKey(
    SequenceAfterBreakevenAction action,
  ) => switch (action) {
    SequenceAfterBreakevenAction.keepCurrent => 'sequence.afterBe.keepCurrent',
    SequenceAfterBreakevenAction.moveToNext => 'sequence.afterBe.moveToNext',
    SequenceAfterBreakevenAction.doNotCountStep =>
      'sequence.afterBe.doNotCountStep',
    SequenceAfterBreakevenAction.recalculate => 'sequence.afterBe.recalculate',
  };

  String _accountName(PropFirmPortfolio portfolio, String id) =>
      portfolio.accounts.where((item) => item.id == id).firstOrNull?.name ?? id;

  String _planId(String portfolioId, DateTime now) =>
      'sequence-$portfolioId-${now.microsecondsSinceEpoch}';

  double _oneDecimal(double value) => (value * 10).round() / 10;

  bool _sameList(List<String> left, List<String> right) {
    if (left.length != right.length) return false;
    for (var index = 0; index < left.length; index++) {
      if (left[index] != right[index]) return false;
    }
    return true;
  }

  List<SequenceReason> _deduplicate(List<SequenceReason> values) {
    final keys = <String>{};
    return values
        .where((item) => keys.add('${item.messageKey}:${item.values}'))
        .toList(growable: false);
  }
}

extension<T> on Iterable<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
