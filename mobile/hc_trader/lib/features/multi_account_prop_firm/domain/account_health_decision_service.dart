import 'dart:math' as math;

import 'prop_firm_calculation_service.dart';
import 'prop_firm_models.dart';

/// Calculates firm-rule loss room without connecting to a broker. Missing
/// inputs are handled conservatively and exposed through [isEstimated].
class AccountLossLimitService {
  const AccountLossLimitService();

  LossLimitSnapshot calculate(PropFirmAccount account) {
    final situation = account.situation;
    var estimated = false;

    final maximumPercent = _validPercent(situation.maximumLossPercentage)
        ? situation.maximumLossPercentage
        : 10.0;
    if (!_validPercent(situation.maximumLossPercentage)) estimated = true;
    final maximumBase = _positive(account.startingBalance)
        ? account.startingBalance
        : account.accountSize;
    if (!_positive(account.startingBalance)) estimated = true;
    final configuredMaximumAmount =
        account.maximumLossType == LossLimitType.percentage
        ? account.accountSize * account.maximumLossLimit / 100
        : account.maximumLossLimit;
    final maximumAmount = _firstPositive([
      if (account.maximumLossType == LossLimitType.fixedAmount)
        situation.maximumLossAmount,
      configuredMaximumAmount,
      if (account.maximumLossType == LossLimitType.percentage)
        situation.maximumLossAmount,
      maximumBase * maximumPercent / 100,
    ]);

    final dailyPercent = _validPercent(situation.dailyLossPercentage)
        ? situation.dailyLossPercentage
        : 5.0;
    if (!_validPercent(situation.dailyLossPercentage)) estimated = true;
    final dailyBase = switch (situation.dailyLossCalculationMethod) {
      DailyLossCalculationMethod.equityBased =>
        situation.dailyStartingEquity ??
            situation.dailyStartingBalance ??
            account.manuallyEnteredEquity ??
            account.startingBalance,
      DailyLossCalculationMethod.balanceBased =>
        situation.dailyStartingBalance ?? account.startingBalance,
      DailyLossCalculationMethod.realisedOnly ||
      DailyLossCalculationMethod.realisedAndUnrealised ||
      DailyLossCalculationMethod.custom =>
        situation.dailyStartingBalance ?? account.accountSize,
    };
    if (!_positive(dailyBase) ||
        (situation.dailyStartingBalance == null &&
            situation.dailyStartingEquity == null)) {
      estimated = true;
    }
    final configuredDailyAmount =
        account.dailyLossType == LossLimitType.percentage
        ? account.accountSize * account.dailyLossLimit / 100
        : account.dailyLossLimit;
    final dailyAmount = _firstPositive([
      if (account.dailyLossType == LossLimitType.fixedAmount)
        situation.dailyLossAmount,
      configuredDailyAmount,
      if (account.dailyLossType == LossLimitType.percentage)
        situation.dailyLossAmount,
      math.max(0.0, dailyBase) * dailyPercent / 100,
    ]);

    final realisedLoss = math.max(
      math.max(0.0, -situation.todayRealisedProfitLoss),
      math.max(0.0, situation.lossAlreadyRecordedToday),
    );
    final unrealisedLoss = math.max(
      0.0,
      -(situation.todayUnrealisedProfitLoss ?? 0),
    );
    final openRisk = math.max(0.0, situation.manuallyEnteredOpenRisk);
    final includeFloating = switch (situation.dailyLossCalculationMethod) {
      DailyLossCalculationMethod.realisedOnly ||
      DailyLossCalculationMethod.balanceBased => false,
      DailyLossCalculationMethod.realisedAndUnrealised ||
      DailyLossCalculationMethod.equityBased ||
      DailyLossCalculationMethod.custom => true,
    };
    final effectiveTodayProfitLoss =
        situation.todayRealisedProfitLoss +
        (includeFloating ? situation.todayUnrealisedProfitLoss ?? 0 : 0);
    final effectiveOverallProfitLoss =
        account.overallRealisedProfitLoss +
        (account.drawdownType == DrawdownType.balanceBased ||
                account.drawdownType == DrawdownType.static
            ? 0
            : account.overallUnrealisedProfitLoss);
    final derivedLoss = switch (situation.dailyLossCalculationMethod) {
      DailyLossCalculationMethod.realisedOnly => realisedLoss,
      DailyLossCalculationMethod.realisedAndUnrealised =>
        realisedLoss + unrealisedLoss,
      DailyLossCalculationMethod.balanceBased => math.max(
        realisedLoss,
        math.max(
          0.0,
          (situation.dailyStartingBalance ?? account.startingBalance) -
              account.currentBalance,
        ),
      ),
      DailyLossCalculationMethod.equityBased => math.max(
        realisedLoss + unrealisedLoss,
        math.max(
          0.0,
          (situation.dailyStartingEquity ??
                  situation.dailyStartingBalance ??
                  account.startingBalance) -
              account.referenceEquity,
        ),
      ),
      DailyLossCalculationMethod.custom => realisedLoss + unrealisedLoss,
    };
    final dailyUsed = math.max(0.0, derivedLoss) + openRisk;
    final safetyBuffer = account.safetyBufferPercentage.clamp(0, 100) / 100;
    final dailyRemaining = math.max(
      0.0,
      dailyAmount * (1 - safetyBuffer) - dailyUsed,
    );

    final trailingReference = situation.trailingReferenceBalance;
    final overallReference = switch (account.drawdownType) {
      DrawdownType.static ||
      DrawdownType.equityBased => account.startingBalance,
      DrawdownType.balanceBased => account.startingBalance,
      DrawdownType.trailing || DrawdownType.endOfDayTrailing =>
        trailingReference ??
            math.max(account.startingBalance, account.currentBalance),
      DrawdownType.custom => trailingReference ?? account.startingBalance,
    };
    if ({
          DrawdownType.trailing,
          DrawdownType.endOfDayTrailing,
          DrawdownType.custom,
        }.contains(account.drawdownType) &&
        trailingReference == null) {
      estimated = true;
    }
    final currentReference = switch (account.drawdownType) {
      DrawdownType.balanceBased => account.currentBalance,
      DrawdownType.static => account.currentBalance,
      DrawdownType.equityBased ||
      DrawdownType.trailing ||
      DrawdownType.endOfDayTrailing ||
      DrawdownType.custom => account.referenceEquity,
    };
    if (account.drawdownType == DrawdownType.equityBased &&
        account.manuallyEnteredEquity == null) {
      estimated = true;
    }
    final maximumFloor = overallReference - maximumAmount;
    final rawMaximumRemaining = currentReference - maximumFloor - openRisk;
    final maximumRemaining = math.max(
      0.0,
      rawMaximumRemaining - maximumAmount * safetyBuffer,
    );
    final maximumUsed = math.max(0.0, maximumAmount - rawMaximumRemaining);
    final personalDailyLimit = _firstPositive([
      account.personalDailyStopLossAmount,
      dailyAmount * (1 - safetyBuffer),
    ]);
    final personalDailyRiskUsed = math.max(0.0, dailyUsed);
    final personalDailyRiskRemaining = math.max(
      0.0,
      personalDailyLimit - personalDailyRiskUsed,
    );

    return LossLimitSnapshot(
      maximumLossAmount: maximumAmount,
      maximumLossFloor: maximumFloor,
      dailyLossAmount: dailyAmount,
      effectiveTodayProfitLoss: effectiveTodayProfitLoss,
      effectiveOverallProfitLoss: effectiveOverallProfitLoss,
      dailyLossUsed: dailyUsed,
      dailyLossRemaining: dailyRemaining,
      maximumLossUsed: maximumUsed,
      maximumLossRemaining: maximumRemaining,
      dailyUsagePercent: _percent(dailyUsed, dailyAmount),
      maximumUsagePercent: _percent(maximumUsed, maximumAmount),
      openRiskIncluded: openRisk,
      personalDailyRiskUsed: personalDailyRiskUsed,
      personalDailyRiskRemaining: personalDailyRiskRemaining,
      isEstimated: estimated || dailyAmount <= 0 || maximumAmount <= 0,
    );
  }

  static bool _positive(double value) => value.isFinite && value > 0;

  static bool _validPercent(double value) =>
      value.isFinite && value > 0 && value <= 100;

  static double _firstPositive(List<double> values) =>
      values.firstWhere(_positive, orElse: () => 0);

  static double _percent(double used, double limit) =>
      limit <= 0 ? (used > 0 ? 100 : 0) : (used / limit * 100).clamp(0, 999);
}

class AccountHealthService {
  const AccountHealthService({
    this.lossLimits = const AccountLossLimitService(),
  });

  final AccountLossLimitService lossLimits;

  AccountHealthAssessment assess(
    PropFirmAccount account, {
    required DateTime now,
  }) {
    final loss = lossLimits.calculate(account);
    final payout = assessPayout(account, now: now);
    final missing = _missingData(account, payout);
    final status = _classify(account, loss, payout, now);
    final normalRisk = math.max(0.0, account.maxRiskPerTrade);
    final safetyReserve = math.max(
      normalRisk,
      account.situation.payoutSafetyBuffer,
    );
    final roomAfterReserve = math.max(
      0.0,
      math.min(loss.dailyLossRemaining, loss.maximumLossRemaining) -
          safetyReserve,
    );
    final multiplier = switch (status) {
      AccountHealthStatus.healthy => 1.0,
      AccountHealthStatus.caution || AccountHealthStatus.payoutProgress => .5,
      AccountHealthStatus.highRisk || AccountHealthStatus.recovery => .35,
      AccountHealthStatus.critical => 0.0,
      AccountHealthStatus.waitingForPayoutDate =>
        payout.tradingDaysRemaining > 0 ? .25 : 0.0,
      AccountHealthStatus.payoutEligible => 0.0,
      AccountHealthStatus.nearDailyLossLimit ||
      AccountHealthStatus.nearMaximumLossLimit ||
      AccountHealthStatus.dailyLocked ||
      AccountHealthStatus.payoutProtection ||
      AccountHealthStatus.paused ||
      AccountHealthStatus.breached ||
      AccountHealthStatus.archived => 0.0,
    };
    final suggestedRisk = math.min(normalRisk * multiplier, roomAfterReserve);
    final reasons = _reasons(account, loss, payout, status);
    final confidence = missing.isEmpty
        ? RecommendationConfidence.completeData
        : missing.length >= 3
        ? RecommendationConfidence.insufficientData
        : RecommendationConfidence.partiallyCompleteData;
    final manualMapped = _manualStatus(account.situation.manualStatus);

    return AccountHealthAssessment(
      accountId: account.id,
      status: status,
      manualStatus: account.situation.manualStatus,
      manualStatusDiffers: manualMapped != status,
      lossLimits: loss,
      payout: payout,
      reasons: List.unmodifiable(reasons),
      recommendedAction: _action(account, status, payout, suggestedRisk),
      maximumSuggestedRisk: suggestedRisk,
      missingData: List.unmodifiable(missing),
      confidence: confidence,
      assessedAt: now,
    );
  }

  PayoutEligibilityAssessment assessPayout(
    PropFirmAccount account, {
    required DateTime now,
  }) {
    final situation = account.situation;
    final requiredProfit = math.max(0.0, account.minimumPayout);
    final eligibleProfit = math.max(
      0.0,
      situation.currentPayoutEligibleProfit ?? account.currentProfit,
    );
    final profitReached =
        requiredProfit > 0 && eligibleProfit >= requiredProfit;
    final daysRemaining = math.max(
      0,
      account.minimumTradingDays - account.completedTradingDays,
    );
    final tradingDaysComplete = daysRemaining == 0;
    final consistencySatisfied =
        account.consistencyRuleLimit <= 0 ||
        situation.consistencyRuleSatisfied == true;
    final requestDate =
        situation.nextPayoutRequestDate ??
        situation.earliestPayoutDate ??
        account.nextPayoutDate;
    final today = _dateOnly(now);
    final requestDay = requestDate == null ? null : _dateOnly(requestDate);
    final requestDateReached =
        requestDay != null && !today.isBefore(requestDay);
    final daysUntil = requestDay == null
        ? 0
        : math.max(0, requestDay.difference(today).inDays);
    final manualConfirmed =
        situation.eligibilityAutomaticallyCalculated ||
        situation.payoutCanCurrentlyBeRequested == true;
    final coreComplete =
        profitReached && tradingDaysComplete && consistencySatisfied;
    final eligible = coreComplete && requestDateReached && manualConfirmed;
    final waiting = coreComplete && requestDay != null && !requestDateReached;
    final threshold =
        requiredProfit + math.max(0.0, situation.payoutSafetyBuffer);
    final requirements = <RecommendationReason>[
      if (!profitReached)
        RecommendationReason('health.payout.profitRemaining', {
          'amount': math.max(0.0, requiredProfit - eligibleProfit),
        }),
      if (!tradingDaysComplete)
        RecommendationReason('health.payout.daysRemaining', {
          'count': daysRemaining,
        }),
      if (!consistencySatisfied)
        const RecommendationReason('health.payout.consistencyIncomplete'),
      if (coreComplete && requestDate == null)
        const RecommendationReason('health.payout.dateMissing'),
      if (waiting)
        RecommendationReason('health.payout.waitingDays', {'count': daysUntil}),
      if (coreComplete &&
          requestDateReached &&
          !situation.eligibilityAutomaticallyCalculated &&
          !manualConfirmed)
        const RecommendationReason('health.payout.manualConfirmationNeeded'),
    ];
    return PayoutEligibilityAssessment(
      eligibleProfit: eligibleProfit,
      requiredProfit: requiredProfit,
      profitReached: profitReached,
      tradingDaysComplete: tradingDaysComplete,
      consistencySatisfied: consistencySatisfied,
      requestDateReached: requestDateReached,
      manuallyConfirmed: manualConfirmed,
      eligible: eligible,
      waitingForDate: waiting,
      tradingDaysRemaining: daysRemaining,
      daysUntilRequest: daysUntil,
      protectionThreshold: threshold,
      profitAboveThreshold: math.max(0.0, eligibleProfit - requiredProfit),
      requirements: List.unmodifiable(requirements),
    );
  }

  AccountHealthStatus _classify(
    PropFirmAccount account,
    LossLimitSnapshot loss,
    PayoutEligibilityAssessment payout,
    DateTime now,
  ) {
    final manual = account.situation.manualStatus;
    if (manual == ManualAccountHealthStatus.breached ||
        account.status == AccountStatus.breached ||
        loss.dailyLimitBreached ||
        loss.maximumLimitBreached) {
      return AccountHealthStatus.breached;
    }
    if (manual == ManualAccountHealthStatus.archived || account.isArchived) {
      return AccountHealthStatus.archived;
    }
    if (math.max(loss.dailyUsagePercent, loss.maximumUsagePercent) >=
        account.situation.highRiskUpperPercent) {
      return AccountHealthStatus.critical;
    }
    final protectionThreatened =
        payout.profitReached &&
        account.maxRiskPerTrade > 0 &&
        payout.profitAboveThreshold < account.maxRiskPerTrade;
    if (account.isManuallyProtected ||
        account.situation.protectUntilPayout ||
        manual == ManualAccountHealthStatus.payoutProtection ||
        {
          AccountStatus.payoutProtection,
          AccountStatus.payoutReady,
          AccountStatus.payoutRequested,
        }.contains(account.status) ||
        (payout.waitingForDate &&
            payout.tradingDaysRemaining == 0 &&
            protectionThreatened)) {
      return AccountHealthStatus.payoutProtection;
    }
    if (manual == ManualAccountHealthStatus.paused ||
        !account.isAvailableToday ||
        account.isManuallyExcluded ||
        account.status == AccountStatus.paused ||
        account.status == AccountStatus.portfolioLocked ||
        {
              AccountStatus.coolingAfterWin,
              AccountStatus.coolingAfterLoss,
            }.contains(account.status) &&
            (account.cooldownUntil == null ||
                now.isBefore(account.cooldownUntil!))) {
      return AccountHealthStatus.paused;
    }
    if (manual == ManualAccountHealthStatus.dailyLocked ||
        account.status == AccountStatus.dailyLocked) {
      return AccountHealthStatus.dailyLocked;
    }
    if (account.tradesToday >= account.maxTradesPerDay ||
        loss.dailyUsagePercent >=
            account.situation.dailySafetyThresholdPercent) {
      return AccountHealthStatus.dailyLocked;
    }
    final normalRisk = math.max(0.0, account.maxRiskPerTrade);
    final cannotFitNormalRisk =
        normalRisk > 0 &&
        math.min(loss.dailyLossRemaining, loss.maximumLossRemaining) <
            normalRisk;
    if (cannotFitNormalRisk &&
        loss.dailyLossRemaining <= loss.maximumLossRemaining) {
      return AccountHealthStatus.nearDailyLossLimit;
    }
    if (cannotFitNormalRisk) {
      return AccountHealthStatus.nearMaximumLossLimit;
    }
    if (payout.waitingForDate) {
      return AccountHealthStatus.waitingForPayoutDate;
    }
    if (payout.eligible) return AccountHealthStatus.payoutEligible;
    if (account.status == AccountStatus.recovery ||
        manual == ManualAccountHealthStatus.recovery) {
      return AccountHealthStatus.recovery;
    }
    final highUsage = math.max(
      loss.dailyUsagePercent,
      loss.maximumUsagePercent,
    );
    if (highUsage >= account.situation.cautionUpperPercent ||
        account.consecutiveLosses >= 2) {
      return AccountHealthStatus.highRisk;
    }
    if (highUsage >= account.situation.healthyUpperPercent ||
        account.consecutiveLosses > 0 ||
        account.situation.emotionallyAffected ||
        account.situation.lastTradeFollowedPlan == false) {
      return AccountHealthStatus.caution;
    }
    if (payout.profitReached) return AccountHealthStatus.payoutProgress;
    return AccountHealthStatus.healthy;
  }

  List<RecommendationReason> _missingData(
    PropFirmAccount account,
    PayoutEligibilityAssessment payout,
  ) => [
    if (account.currentBalance <= 0)
      const RecommendationReason('health.missing.currentBalance'),
    if (account.situation.dailyStartingBalance == null &&
        account.situation.dailyStartingEquity == null)
      const RecommendationReason('health.missing.dailyStartingBalance'),
    if (account.dailyLossLimit <= 0 && account.situation.dailyLossAmount <= 0)
      const RecommendationReason('health.missing.dailyLimit'),
    if (account.maximumLossLimit <= 0 &&
        account.situation.maximumLossAmount <= 0)
      const RecommendationReason('health.missing.maximumLimit'),
    if (account.situation.resetHour == null ||
        account.situation.resetTimeZone.trim().isEmpty)
      const RecommendationReason('health.missing.resetTime'),
    if (payout.profitReached &&
        account.nextPayoutDate == null &&
        account.situation.earliestPayoutDate == null &&
        account.situation.nextPayoutRequestDate == null)
      const RecommendationReason('health.missing.payoutDate'),
    if (account.drawdownType == DrawdownType.equityBased &&
        account.manuallyEnteredEquity == null)
      const RecommendationReason('health.missing.currentEquity'),
  ];

  List<RecommendationReason> _reasons(
    PropFirmAccount account,
    LossLimitSnapshot loss,
    PayoutEligibilityAssessment payout,
    AccountHealthStatus status,
  ) => [
    RecommendationReason('health.reason.dailyUsage', {
      'percent': loss.dailyUsagePercent,
      'remaining': loss.dailyLossRemaining,
    }),
    RecommendationReason('health.reason.maximumUsage', {
      'percent': loss.maximumUsagePercent,
      'remaining': loss.maximumLossRemaining,
    }),
    if (loss.openRiskIncluded > 0)
      RecommendationReason('health.reason.openRiskIncluded', {
        'amount': loss.openRiskIncluded,
      }),
    if (loss.isEstimated) const RecommendationReason('health.reason.estimate'),
    if (account.situation.emotionallyAffected)
      const RecommendationReason('health.reason.emotionalWarning'),
    if (account.consecutiveLosses > 0)
      RecommendationReason('health.reason.consecutiveLosses', {
        'count': account.consecutiveLosses,
      }),
    if (payout.profitReached)
      const RecommendationReason('health.reason.payoutProfitReached'),
    if (status == AccountHealthStatus.waitingForPayoutDate ||
        status == AccountHealthStatus.payoutProtection)
      RecommendationReason('health.reason.payoutWait', {
        'count': payout.daysUntilRequest,
      }),
  ];

  RecommendationReason _action(
    PropFirmAccount account,
    AccountHealthStatus status,
    PayoutEligibilityAssessment payout,
    double risk,
  ) => switch (status) {
    AccountHealthStatus.healthy => const RecommendationReason(
      'health.action.continueWithinLimits',
    ),
    AccountHealthStatus.caution ||
    AccountHealthStatus.highRisk ||
    AccountHealthStatus.recovery => RecommendationReason(
      'health.action.reduceRisk',
      {'amount': risk},
    ),
    AccountHealthStatus.payoutProgress => const RecommendationReason(
      'health.action.completeRequirements',
    ),
    AccountHealthStatus.payoutEligible => const RecommendationReason(
      'health.action.reviewPayoutRequest',
    ),
    AccountHealthStatus.waitingForPayoutDate =>
      payout.tradingDaysRemaining > 0
          ? const RecommendationReason('health.action.completeRequirements')
          : const RecommendationReason('health.action.protectUntilPayout'),
    AccountHealthStatus.nearDailyLossLimit || AccountHealthStatus.dailyLocked =>
      const RecommendationReason('health.action.stopAccountToday'),
    AccountHealthStatus.nearMaximumLossLimit ||
    AccountHealthStatus.critical ||
    AccountHealthStatus.paused ||
    AccountHealthStatus.breached ||
    AccountHealthStatus.archived => const RecommendationReason(
      'health.action.stopAccount',
    ),
    AccountHealthStatus.payoutProtection => const RecommendationReason(
      'health.action.protectUntilPayout',
    ),
  };

  AccountHealthStatus _manualStatus(ManualAccountHealthStatus status) =>
      switch (status) {
        ManualAccountHealthStatus.healthy => AccountHealthStatus.healthy,
        ManualAccountHealthStatus.moderateRisk => AccountHealthStatus.caution,
        ManualAccountHealthStatus.nearDailyLossLimit =>
          AccountHealthStatus.nearDailyLossLimit,
        ManualAccountHealthStatus.nearMaximumLossLimit =>
          AccountHealthStatus.nearMaximumLossLimit,
        ManualAccountHealthStatus.dailyLocked =>
          AccountHealthStatus.dailyLocked,
        ManualAccountHealthStatus.recovery => AccountHealthStatus.recovery,
        ManualAccountHealthStatus.payoutProgress =>
          AccountHealthStatus.payoutProgress,
        ManualAccountHealthStatus.payoutEligible =>
          AccountHealthStatus.payoutEligible,
        ManualAccountHealthStatus.waitingForPayoutDate =>
          AccountHealthStatus.waitingForPayoutDate,
        ManualAccountHealthStatus.payoutProtection =>
          AccountHealthStatus.payoutProtection,
        ManualAccountHealthStatus.paused => AccountHealthStatus.paused,
        ManualAccountHealthStatus.breached => AccountHealthStatus.breached,
        ManualAccountHealthStatus.archived => AccountHealthStatus.archived,
      };
}

class PortfolioHealthDecisionService {
  const PortfolioHealthDecisionService({
    this.health = const AccountHealthService(),
    this.calculations = const PropFirmCalculationService(),
  });

  final AccountHealthService health;
  final PropFirmCalculationService calculations;

  PortfolioRecommendation recommend(
    PropFirmPortfolio portfolio, {
    required DateTime now,
  }) {
    final assessments = <String, AccountHealthAssessment>{
      for (final account in portfolio.accounts)
        account.id: health.assess(account, now: now),
    };
    final active = portfolio.activeAccounts;
    final restricted = active
        .where((account) {
          final assessment = assessments[account.id]!;
          return assessment.isRestricted ||
              assessment.status == AccountHealthStatus.nearDailyLossLimit ||
              assessment.status == AccountHealthStatus.nearMaximumLossLimit;
        })
        .map((account) => account.id)
        .toList(growable: false);
    final stopConditions = <RecommendationReason>[
      const RecommendationReason('portfolio.stop.dailyLoss'),
      const RecommendationReason('portfolio.stop.tradeOrLossCount'),
      const RecommendationReason('portfolio.stop.emotionalRisk'),
    ];
    final anyEmotional = active.any(
      (account) => account.situation.emotionallyAffected,
    );
    final duplicateLosingIdea = _hasDuplicateLosingIdea(portfolio, now);
    if (calculations.hasReachedGlobalStop(portfolio, now) ||
        anyEmotional ||
        duplicateLosingIdea) {
      return PortfolioRecommendation(
        type: PortfolioRecommendationType.stopPortfolio,
        restrictedAccountIds: restricted,
        reasons: [
          RecommendationReason(
            anyEmotional
                ? 'portfolio.reason.emotionalStop'
                : duplicateLosingIdea
                ? 'portfolio.reason.duplicateLosingIdea'
                : 'portfolio.reason.globalStop',
          ),
        ],
        stopConditions: stopConditions,
        confidence: _confidence(assessments.values),
        accountAssessments: Map.unmodifiable(assessments),
        generatedAt: now,
      );
    }

    final candidates = active.where((account) {
      final assessment = assessments[account.id]!;
      return !assessment.isRestricted &&
          assessment.status != AccountHealthStatus.nearDailyLossLimit &&
          assessment.status != AccountHealthStatus.nearMaximumLossLimit;
    }).toList();
    candidates.sort((left, right) {
      final leftAssessment = assessments[left.id]!;
      final rightAssessment = assessments[right.id]!;
      final statusCompare = _statusScore(
        rightAssessment.status,
      ).compareTo(_statusScore(leftAssessment.status));
      if (statusCompare != 0) return statusCompare;
      final roomCompare = rightAssessment.lossLimits.dailyLossRemaining
          .compareTo(leftAssessment.lossLimits.dailyLossRemaining);
      if (roomCompare != 0) return roomCompare;
      return left.name.compareTo(right.name);
    });

    if (candidates.isEmpty) {
      return PortfolioRecommendation(
        type: active.length == 1 && restricted.length == 1
            ? PortfolioRecommendationType.stopSpecificAccount
            : PortfolioRecommendationType.noEligibleAccount,
        restrictedAccountIds: restricted,
        reasons: const [
          RecommendationReason('portfolio.reason.noEligibleAccount'),
        ],
        stopConditions: stopConditions,
        confidence: _confidence(assessments.values),
        accountAssessments: Map.unmodifiable(assessments),
        generatedAt: now,
      );
    }

    final selected = candidates.first;
    final selectedAssessment = assessments[selected.id]!;
    final protected = assessments.values.where(
      (value) => value.status == AccountHealthStatus.payoutProtection,
    );
    final activeIsRestricted =
        portfolio.activeAccountId != null &&
        restricted.contains(portfolio.activeAccountId);
    final needsRequirements =
        selectedAssessment.payout.profitReached &&
        !selectedAssessment.payout.tradingDaysComplete;
    final reduced =
        {
          AccountHealthStatus.caution,
          AccountHealthStatus.highRisk,
          AccountHealthStatus.recovery,
        }.contains(selectedAssessment.status) ||
        selectedAssessment.lossLimits.dailyUsagePercent >= 50 ||
        selectedAssessment.lossLimits.maximumUsagePercent >= 50 ||
        calculations.realisedLossToday(portfolio, now) > 0;
    final type = activeIsRestricted
        ? PortfolioRecommendationType.switchAccount
        : selectedAssessment.status == AccountHealthStatus.payoutEligible
        ? PortfolioRecommendationType.protectForPayout
        : needsRequirements
        ? PortfolioRecommendationType.completeRemainingRequirementsCarefully
        : reduced
        ? PortfolioRecommendationType.continueWithReducedRisk
        : PortfolioRecommendationType.continueNormally;
    final reasons = <RecommendationReason>[
      RecommendationReason('portfolio.reason.selectedAccount', {
        'account': selected.name,
        'dailyPercent': selectedAssessment.lossLimits.dailyUsagePercent,
        'maximumPercent': selectedAssessment.lossLimits.maximumUsagePercent,
      }),
      if (activeIsRestricted)
        const RecommendationReason('portfolio.reason.switchFromRestricted'),
      if (reduced) const RecommendationReason('portfolio.reason.reducedRisk'),
      if (needsRequirements)
        RecommendationReason('portfolio.reason.requirementsRemain', {
          'count': selectedAssessment.payout.tradingDaysRemaining,
        }),
      if (protected.isNotEmpty)
        RecommendationReason('portfolio.reason.protectedAccounts', {
          'count': protected.length,
        }),
      if (restricted.isNotEmpty)
        RecommendationReason('portfolio.reason.restrictedAccounts', {
          'count': restricted.length,
        }),
    ];
    return PortfolioRecommendation(
      type: type,
      recommendedAccountId: selected.id,
      restrictedAccountIds: restricted,
      reasons: List.unmodifiable(reasons),
      stopConditions: stopConditions,
      maximumSuggestedRisk: selectedAssessment.maximumSuggestedRisk,
      confidence: _confidence(assessments.values),
      accountAssessments: Map.unmodifiable(assessments),
      generatedAt: now,
    );
  }

  RecommendationConfidence _confidence(
    Iterable<AccountHealthAssessment> assessments,
  ) {
    if (assessments.isEmpty) return RecommendationConfidence.insufficientData;
    if (assessments.any(
      (item) => item.confidence == RecommendationConfidence.insufficientData,
    )) {
      return RecommendationConfidence.insufficientData;
    }
    if (assessments.any(
      (item) =>
          item.confidence == RecommendationConfidence.partiallyCompleteData,
    )) {
      return RecommendationConfidence.partiallyCompleteData;
    }
    return RecommendationConfidence.completeData;
  }

  int _statusScore(AccountHealthStatus status) => switch (status) {
    AccountHealthStatus.healthy => 100,
    AccountHealthStatus.caution => 80,
    AccountHealthStatus.payoutProgress => 70,
    AccountHealthStatus.highRisk => 60,
    AccountHealthStatus.critical => 10,
    AccountHealthStatus.recovery => 50,
    AccountHealthStatus.payoutEligible => 30,
    AccountHealthStatus.waitingForPayoutDate => 20,
    AccountHealthStatus.nearDailyLossLimit ||
    AccountHealthStatus.nearMaximumLossLimit ||
    AccountHealthStatus.dailyLocked ||
    AccountHealthStatus.payoutProtection ||
    AccountHealthStatus.paused ||
    AccountHealthStatus.breached ||
    AccountHealthStatus.archived => 0,
  };

  bool _hasDuplicateLosingIdea(PropFirmPortfolio portfolio, DateTime now) {
    final plans = {for (final plan in portfolio.plannedTrades) plan.id: plan};
    final accountsByIdea = <String, Set<String>>{};
    for (final result in calculations.resultsForTradingDay(portfolio, now)) {
      if (!result.isLoss || result.plannedTradeId == null) continue;
      final plan = plans[result.plannedTradeId];
      if (plan == null) continue;
      final idea =
          '${plan.instrument.trim().toUpperCase()}:${plan.direction.name}';
      accountsByIdea.putIfAbsent(idea, () => <String>{}).add(result.accountId);
    }
    return accountsByIdea.values.any((accountIds) => accountIds.length > 1);
  }
}

DateTime _dateOnly(DateTime value) =>
    DateTime(value.year, value.month, value.day);
