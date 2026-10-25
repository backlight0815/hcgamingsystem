import 'dart:math' as math;

import 'account_health_decision_service.dart';
import 'prop_firm_models.dart';

enum StrategyHealthBand { healthy, moderate, warning, critical, unavailable }

class HealthScoreFactor {
  const HealthScoreFactor(this.key, this.points, this.maximumPoints);

  final String key;
  final double points;
  final double maximumPoints;
}

class AccountHealthScore {
  const AccountHealthScore({
    required this.accountId,
    required this.score,
    required this.band,
    required this.factors,
    required this.lossLimits,
    required this.targetProgress,
    required this.payoutProgress,
    required this.isEvaluationPassed,
    required this.isPayoutEligible,
    required this.unavailableReasonKey,
  });

  final String accountId;
  final double score;
  final StrategyHealthBand band;
  final List<HealthScoreFactor> factors;
  final LossLimitSnapshot lossLimits;
  final double targetProgress;
  final double payoutProgress;
  final bool isEvaluationPassed;
  final bool isPayoutEligible;
  final String? unavailableReasonKey;

  bool get isEligible => unavailableReasonKey == null;
}

/// One transparent 0–100 score used by the simplified overview, details and
/// sequencing workflow. The base weights are maximum-loss room 35, daily-loss
/// room 35, equity condition 15, target/payout progress 10 and data/availability
/// 5. A recent loss streak can deduct up to 10 points without changing the
/// underlying loss-capacity measurements.
class AccountHealthScoreService {
  const AccountHealthScoreService({
    this.lossLimits = const AccountLossLimitService(),
  });

  final AccountLossLimitService lossLimits;

  AccountHealthScore calculate(
    PropFirmAccount account, {
    required DateTime now,
  }) {
    final loss = lossLimits.calculate(account);
    final maximumRoom = _remainingPercent(
      loss.maximumLossRemaining,
      loss.maximumLossAmount,
    );
    final dailyRoom = _remainingPercent(
      loss.dailyLossRemaining,
      loss.dailyLossAmount,
    );
    final maximumPoints = maximumRoom * 35;
    final dailyPoints = dailyRoom * 35;

    final equity = account.referenceEquity;
    final equityDrawdown = math.max(0.0, account.accountSize - equity);
    final equityCapacity = loss.maximumLossAmount <= 0
        ? 0.0
        : (1 - equityDrawdown / loss.maximumLossAmount).clamp(0, 1).toDouble();
    final equityPoints = equityCapacity * 15;

    final profit = math.max(0.0, account.currentProfit);
    final targetProgress = account.profitTarget <= 0
        ? 0.0
        : (profit / account.profitTarget).clamp(0, 1).toDouble();
    final payoutProgress = account.minimumPayout <= 0
        ? 0.0
        : (profit / account.minimumPayout).clamp(0, 1).toDouble();
    final progress = account.isEvaluationAccount
        ? targetProgress
        : account.minimumPayout > 0
        ? payoutProgress
        : 1.0;
    final progressPoints = progress * 10;

    final unavailableReason = _unavailableReason(account, loss, now);
    final temporarilyCooling =
        {
          AccountStatus.coolingAfterLoss,
          AccountStatus.coolingAfterWin,
        }.contains(account.status) &&
        (account.cooldownUntil == null || now.isBefore(account.cooldownUntil!));
    final dataComplete = _hasCompleteData(account);
    final availabilityPoints = unavailableReason == null && dataComplete
        ? 5.0
        : 0.0;
    final recentLossPenalty = math.min(10.0, account.consecutiveLosses * 5.0);
    final raw =
        maximumPoints +
        dailyPoints +
        equityPoints +
        progressPoints +
        availabilityPoints -
        recentLossPenalty;
    // Cooling is an execution safeguard, not a loss of financial health.
    // Keep the account ineligible until the timer ends, but retain its
    // capacity/equity score so a profitable recovery is visible immediately.
    final score = unavailableReason == null || temporarilyCooling
        ? raw.clamp(0, 100).toDouble()
        : 0.0;
    final band = unavailableReason != null && !temporarilyCooling
        ? StrategyHealthBand.unavailable
        : score >= 80
        ? StrategyHealthBand.healthy
        : score >= 60
        ? StrategyHealthBand.moderate
        : score >= 40
        ? StrategyHealthBand.warning
        : StrategyHealthBand.critical;

    return AccountHealthScore(
      accountId: account.id,
      score: _oneDecimal(score),
      band: band,
      factors: List.unmodifiable([
        HealthScoreFactor(
          'healthScore.maximumLossCapacity',
          _oneDecimal(maximumPoints),
          35,
        ),
        HealthScoreFactor(
          'healthScore.dailyLossCapacity',
          _oneDecimal(dailyPoints),
          35,
        ),
        HealthScoreFactor(
          'healthScore.equityCondition',
          _oneDecimal(equityPoints),
          15,
        ),
        HealthScoreFactor(
          'healthScore.targetPayoutProgress',
          _oneDecimal(progressPoints),
          10,
        ),
        HealthScoreFactor(
          'healthScore.dataAvailability',
          availabilityPoints,
          5,
        ),
        if (recentLossPenalty > 0)
          HealthScoreFactor(
            'healthScore.recentLossPenalty',
            -recentLossPenalty,
            0,
          ),
      ]),
      lossLimits: loss,
      targetProgress: targetProgress,
      payoutProgress: payoutProgress,
      isEvaluationPassed:
          account.isEvaluationAccount &&
          account.profitTarget > 0 &&
          profit >= account.profitTarget,
      isPayoutEligible:
          account.isFundedAccount &&
          account.minimumPayout > 0 &&
          profit >= account.minimumPayout &&
          (account.nextPayoutDate == null ||
              !now.isBefore(account.nextPayoutDate!)),
      unavailableReasonKey: unavailableReason,
    );
  }

  String? _unavailableReason(
    PropFirmAccount account,
    LossLimitSnapshot loss,
    DateTime now,
  ) {
    if (!_hasCompleteData(account)) return 'account.unavailable.incomplete';
    if (account.isArchived) return 'account.unavailable.archived';
    if (account.status == AccountStatus.paused || !account.isAvailableToday) {
      return 'account.unavailable.paused';
    }
    if (account.status == AccountStatus.breached || loss.maximumLimitBreached) {
      return 'account.unavailable.maximumLoss';
    }
    if (account.isEvaluationAccount &&
        account.profitTarget > 0 &&
        account.currentProfit >= account.profitTarget) {
      return 'account.unavailable.evaluationPassed';
    }
    if (account.status == AccountStatus.dailyLocked ||
        loss.dailyLimitBreached) {
      return 'account.unavailable.dailyLoss';
    }
    if (account.isManuallyProtected ||
        account.situation.protectUntilPayout ||
        {
          AccountStatus.payoutProtection,
          AccountStatus.payoutReady,
          AccountStatus.payoutRequested,
        }.contains(account.status)) {
      return 'account.unavailable.payoutProtected';
    }
    if (account.status == AccountStatus.portfolioLocked) {
      return 'account.unavailable.cooldown';
    }
    if ({
          AccountStatus.coolingAfterLoss,
          AccountStatus.coolingAfterWin,
        }.contains(account.status) &&
        (account.cooldownUntil == null ||
            now.isBefore(account.cooldownUntil!))) {
      return 'account.unavailable.cooldown';
    }
    return null;
  }

  bool _hasCompleteData(PropFirmAccount account) =>
      account.name.trim().isNotEmpty &&
      account.identifier.trim().isNotEmpty &&
      account.currency.trim().isNotEmpty &&
      account.accountSize.isFinite &&
      account.accountSize > 0 &&
      account.currentBalance.isFinite &&
      account.referenceEquity.isFinite &&
      account.dailyLossLimit.isFinite &&
      account.dailyLossLimit > 0 &&
      account.maximumLossLimit.isFinite &&
      account.maximumLossLimit > 0;

  double _remainingPercent(double remaining, double limit) =>
      limit <= 0 ? 0 : (remaining / limit).clamp(0, 1).toDouble();

  double _oneDecimal(double value) => (value * 10).round() / 10;
}
