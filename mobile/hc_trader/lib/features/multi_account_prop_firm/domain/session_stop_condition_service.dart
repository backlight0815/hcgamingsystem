import 'dart:math' as math;

import 'prop_firm_calculation_service.dart';
import 'prop_firm_models.dart';

String sessionStopReasonTitle(SessionStopReason reason) =>
    sessionStopReasonTitleForCode(reason.code);

String sessionStopReasonDescription(SessionStopReason reason) =>
    sessionStopReasonDescriptionForCode(reason.code);

String sessionStopReasonTitleForCode(
  SessionStopReasonCode code,
) => switch (code) {
  SessionStopReasonCode.combinedLossLimit =>
    'Combined session loss limit reached',
  SessionStopReasonCode.maximumRLoss => 'Maximum R-loss rule triggered',
  SessionStopReasonCode.oneLoss => 'One-loss stop rule triggered',
  SessionStopReasonCode.oneWin => 'One-win stop rule triggered',
  SessionStopReasonCode.consecutiveLosses =>
    'Consecutive-loss stop rule triggered',
  SessionStopReasonCode.maximumTrades => 'Maximum-trades rule triggered',
  SessionStopReasonCode.dailyTarget => 'Daily-target stop rule triggered',
  SessionStopReasonCode.manualStop => 'Stopped manually',
  SessionStopReasonCode.emergencyStop => 'Emergency stop activated',
  SessionStopReasonCode.emotionalRisk => 'Emotional-risk stop rule triggered',
  SessionStopReasonCode.accountSafety => 'Account-safety stop triggered',
  SessionStopReasonCode.payoutProtection => 'Payout-protection stop triggered',
  SessionStopReasonCode.exposureProtection =>
    'Exposure-protection stop triggered',
  SessionStopReasonCode.legacySafetyLock => 'Migrated safety lock',
};

String sessionStopReasonDescriptionForCode(SessionStopReasonCode code) =>
    switch (code) {
      SessionStopReasonCode.combinedLossLimit =>
        'Combined session loss reached the configured amount limit.',
      SessionStopReasonCode.maximumRLoss =>
        'Session R loss reached the configured maximum.',
      SessionStopReasonCode.oneLoss =>
        'The enabled stop-after-one-loss rule was triggered.',
      SessionStopReasonCode.oneWin =>
        'The enabled stop-after-one-win rule was triggered.',
      SessionStopReasonCode.consecutiveLosses =>
        'Consecutive losses reached the configured maximum.',
      SessionStopReasonCode.maximumTrades =>
        'Session trades reached the configured maximum.',
      SessionStopReasonCode.dailyTarget =>
        'The configured daily target was reached.',
      SessionStopReasonCode.manualStop =>
        'The user manually stopped this trading session.',
      SessionStopReasonCode.emergencyStop =>
        'The user activated the session emergency stop.',
      SessionStopReasonCode.emotionalRisk =>
        'The emotional-risk rule requires the complete session to stop.',
      SessionStopReasonCode.accountSafety =>
        'An account-safety rule requires the session to stop.',
      SessionStopReasonCode.payoutProtection =>
        'A payout-protection rule requires the session to stop.',
      SessionStopReasonCode.exposureProtection =>
        'Combined or correlated exposure exceeded the configured rule.',
      SessionStopReasonCode.legacySafetyLock =>
        'A legacy lock was migrated. Review it before clearing the stop.',
    };

class SessionStatusEvaluation {
  const SessionStatusEvaluation({
    required this.status,
    required this.combinedLossUsed,
    required this.combinedLossRemaining,
    required this.usagePercentage,
    this.stopReason,
  }) : assert(
         status != PortfolioSessionStatus.stopped || stopReason != null,
         'A stopped session must contain a stop reason.',
       );

  final PortfolioSessionStatus status;
  final double combinedLossUsed;
  final double combinedLossRemaining;
  final double usagePercentage;
  final SessionStopReason? stopReason;

  bool get isStopped => status == PortfolioSessionStatus.stopped;
}

/// Evaluates only configured portfolio stop rules. Account recommendation and
/// account eligibility are deliberately separate concerns.
class SessionStopConditionService {
  const SessionStopConditionService({
    this.calculations = const PropFirmCalculationService(),
  });

  final PropFirmCalculationService calculations;

  SessionStatusEvaluation evaluate(
    PropFirmPortfolio portfolio, {
    required DateTime now,
  }) {
    final policy = portfolio.riskPolicy;
    final results = calculations.resultsForTradingDay(portfolio, now);
    final realisedLoss = calculations.realisedLossToday(portfolio, now);
    // Loss consumed and open exposure are deliberately separate metrics.
    final used = math.max(0.0, realisedLoss);
    final combinedExposure =
        calculations.activePlannedRisk(portfolio) +
        portfolio.activeAccounts.fold<double>(
          0,
          (sum, account) =>
              sum + math.max(0, account.currentRiskExposureAmount),
        );
    final limit = math.max(0.0, policy.maximumDailyLoss);
    final remaining = math.max(0.0, limit - used);
    final usage = limit <= 0 ? (used > 0 ? 100.0 : 0.0) : used / limit * 100;

    SessionStopReason? reason;
    if (portfolio.stopReason != null &&
        portfolio.stopReason!.code != SessionStopReasonCode.maximumTrades &&
        (portfolio.portfolioLocked ||
            portfolio.sessionStatus == PortfolioSessionStatus.stopped)) {
      reason = portfolio.stopReason;
    } else if (portfolio.emotionalStop && policy.stopAfterEmotionalEvent) {
      reason = _reason(
        SessionStopReasonCode.emotionalRisk,
        SessionStoppedBy.automaticRule,
        now,
      );
    } else if (limit > 0 && used >= limit) {
      reason = _reason(
        SessionStopReasonCode.combinedLossLimit,
        SessionStoppedBy.automaticRule,
        now,
      );
    } else if (policy.maximumCombinedRisk > 0 &&
            combinedExposure > policy.maximumCombinedRisk ||
        _tooManyAccountsOnOneInstrument(portfolio, policy)) {
      reason = _reason(
        SessionStopReasonCode.exposureProtection,
        SessionStoppedBy.exposureProtection,
        now,
      );
    } else if (policy.maximumSessionLossR > 0 &&
        -calculations.rResultToday(portfolio, now) >=
            policy.maximumSessionLossR) {
      reason = _reason(
        SessionStopReasonCode.maximumRLoss,
        SessionStoppedBy.automaticRule,
        now,
      );
    } else if (policy.stopAfterOneLosingTrade &&
        results.any((result) => result.isLoss)) {
      reason = _reason(
        SessionStopReasonCode.oneLoss,
        SessionStoppedBy.automaticRule,
        now,
      );
    } else if (policy.stopAfterOneWinningTrade &&
        results.any((result) => result.isWin)) {
      reason = _reason(
        SessionStopReasonCode.oneWin,
        SessionStoppedBy.automaticRule,
        now,
      );
    } else if (policy.stopAfterConsecutiveLosses &&
        policy.maximumConsecutiveLosses > 0 &&
        calculations.consecutivePortfolioLosses(portfolio) >=
            policy.maximumConsecutiveLosses) {
      reason = _reason(
        SessionStopReasonCode.consecutiveLosses,
        SessionStoppedBy.automaticRule,
        now,
      );
    } else if (policy.stopAfterDailyTarget &&
        (policy.combinedSessionProfitTarget > 0 &&
                calculations.realisedResultToday(portfolio, now) >=
                    policy.combinedSessionProfitTarget ||
            portfolio.accounts.any((account) => account.dailyTargetReached))) {
      reason = _reason(
        SessionStopReasonCode.dailyTarget,
        SessionStoppedBy.automaticRule,
        now,
      );
    }

    if (reason != null) {
      return SessionStatusEvaluation(
        status: PortfolioSessionStatus.stopped,
        stopReason: reason,
        combinedLossUsed: used,
        combinedLossRemaining: remaining,
        usagePercentage: usage,
      );
    }

    final hasActivity =
        portfolio.sessionActive ||
        results.isNotEmpty ||
        portfolio.openPlans.isNotEmpty;
    final status = usage >= 70 || portfolio.accounts.any(_hasSafetyWarning)
        ? PortfolioSessionStatus.warning
        : hasActivity
        ? PortfolioSessionStatus.active
        : portfolio.sessionStatus == PortfolioSessionStatus.ready
        ? PortfolioSessionStatus.ready
        : PortfolioSessionStatus.draft;
    return SessionStatusEvaluation(
      status: status,
      combinedLossUsed: used,
      combinedLossRemaining: remaining,
      usagePercentage: usage,
    );
  }

  bool _hasSafetyWarning(PropFirmAccount account) =>
      account.status == AccountStatus.breached ||
      account.status == AccountStatus.dailyLocked ||
      account.status == AccountStatus.portfolioLocked;

  bool _tooManyAccountsOnOneInstrument(
    PropFirmPortfolio portfolio,
    PortfolioRiskPolicy policy,
  ) {
    if (policy.maximumAccountsSameInstrument < 1) return false;
    final accountsByInstrument = <String, Set<String>>{};
    for (final trade in portfolio.openPlans) {
      final instrument = trade.instrument.trim().toUpperCase();
      if (instrument.isEmpty) continue;
      accountsByInstrument
          .putIfAbsent(instrument, () => <String>{})
          .add(trade.accountId);
    }
    return accountsByInstrument.values.any(
      (accounts) => accounts.length > policy.maximumAccountsSameInstrument,
    );
  }

  SessionStopReason _reason(
    SessionStopReasonCode code,
    SessionStoppedBy stoppedBy,
    DateTime stoppedAt,
  ) => SessionStopReason(
    code: code,
    titleKey: sessionStopReasonTitleForCode(code),
    descriptionKey: sessionStopReasonDescriptionForCode(code),
    stoppedAt: stoppedAt,
    stoppedBy: stoppedBy,
  );
}
