import 'dart:math' as math;

import 'prop_firm_models.dart';
import 'trading_day_service.dart';

class PropFirmCalculationService {
  const PropFirmCalculationService({
    this.tradingDays = const TradingDayService(),
  });

  final TradingDayService tradingDays;

  double plannedRiskAmount(double referenceBalance, double riskPercentage) {
    if (!referenceBalance.isFinite ||
        !riskPercentage.isFinite ||
        referenceBalance < 0 ||
        riskPercentage < 0) {
      throw const FormatException(
        'Risk inputs must be finite and non-negative.',
      );
    }
    return referenceBalance * (riskPercentage / 100);
  }

  double rewardRiskRatio({
    required TradeDirection direction,
    required double entryPrice,
    required double stopLossPrice,
    required double takeProfitPrice,
  }) {
    if ([
      entryPrice,
      stopLossPrice,
      takeProfitPrice,
    ].any((value) => !value.isFinite || value <= 0)) {
      throw const FormatException('Prices must be positive numbers.');
    }
    final riskDistance = direction == TradeDirection.buy
        ? entryPrice - stopLossPrice
        : stopLossPrice - entryPrice;
    final rewardDistance = direction == TradeDirection.buy
        ? takeProfitPrice - entryPrice
        : entryPrice - takeProfitPrice;
    if (riskDistance <= 0 || rewardDistance <= 0) {
      throw const FormatException(
        'Entry, stop loss, and take profit are invalid for this direction.',
      );
    }
    return rewardDistance / riskDistance;
  }

  double remainingDailyDrawdown({
    required double dailyLossLimit,
    required double dailyRealisedLoss,
    required double currentOpenPlannedRisk,
  }) => math.max(
    0,
    dailyLossLimit -
        math.max(0, dailyRealisedLoss) -
        math.max(0, currentOpenPlannedRisk),
  );

  double remainingMaximumDrawdown(PropFirmAccount account) {
    final reference = switch (account.drawdownType) {
      DrawdownType.static => account.startingBalance,
      DrawdownType.balanceBased => account.currentBalance,
      DrawdownType.equityBased => account.referenceEquity,
      DrawdownType.trailing => math.max(
        account.startingBalance,
        account.currentBalance,
      ),
      DrawdownType.endOfDayTrailing =>
        account.situation.trailingReferenceBalance ??
            math.max(account.startingBalance, account.currentBalance),
      DrawdownType.custom =>
        account.situation.trailingReferenceBalance ?? account.startingBalance,
    };
    final drawdownFloor = reference - account.maximumLossLimit;
    return math.max(0, account.referenceEquity - drawdownFloor);
  }

  double portfolioRiskRemaining({
    required double portfolioDailyLossLimit,
    required double totalRealisedLossToday,
    required double totalActivePlannedRisk,
  }) => math.max(
    0,
    portfolioDailyLossLimit -
        math.max(0, totalRealisedLossToday) -
        math.max(0, totalActivePlannedRisk),
  );

  PayoutPlanSummary payoutSummary(
    PropFirmAccount account, {
    required DateTime now,
  }) {
    final requiredGross = math.max(
      account.minimumPayout,
      account.desiredPayout,
    );
    final eligible = math.max(0.0, account.currentProfit);
    final progress = requiredGross <= 0 ? 0.0 : eligible / requiredGross;
    return PayoutPlanSummary(
      requiredGrossProfit: requiredGross,
      eligibleProfit: eligible,
      progress: progress,
      remainingProfit: math.max(0, requiredGross - eligible),
      estimatedNetPayout: requiredGross * account.profitSplit.clamp(0, 1),
      tradingDaysRemaining: math.max(
        0,
        account.minimumTradingDays - account.completedTradingDays,
      ),
      isDateEligible:
          account.nextPayoutDate == null ||
          !now.isBefore(account.nextPayoutDate!),
      suggestedProtectionBuffer: math.max(
        account.maxRiskPerTrade,
        requiredGross * .1,
      ),
    );
  }

  RecoveryPlanSummary recoverySummary(
    PropFirmAccount account, {
    required RecoveryMode mode,
  }) {
    final required = math.max(
      0.0,
      account.startingBalance - account.currentBalance,
    );
    final multiplier = switch (mode) {
      RecoveryMode.conservative => .5,
      RecoveryMode.standard => 1.0,
      RecoveryMode.pauseAndReview => 0.0,
    };
    final risk = account.maxRiskPerTrade * multiplier;
    final requiredR = risk <= 0 ? 0.0 : required / risk;
    return RecoveryPlanSummary(
      mode: mode,
      recoveryRequired: required,
      riskPerTrade: risk,
      requiredNetR: requiredR,
      fullWinsAtTwoR: requiredR <= 0 ? 0 : (requiredR / 2).ceil(),
      remainingBuffer: math.max(0, account.maximumLossLimit - account.drawdown),
    );
  }

  String tradingDayKey(DateTime instant, PortfolioRiskPolicy policy) {
    return tradingDays.tradingDayKey(instant, policy);
  }

  List<RecordedTradeResult> resultsForTradingDay(
    PropFirmPortfolio portfolio,
    DateTime now,
  ) {
    final key = tradingDayKey(now, portfolio.riskPolicy);
    return portfolio.results
        .where(
          (result) =>
              tradingDayKey(result.resultTime, portfolio.riskPolicy) == key,
        )
        .toList(growable: false);
  }

  double realisedLossToday(PropFirmPortfolio portfolio, DateTime now) =>
      resultsForTradingDay(portfolio, now)
          .where((result) => result.amount < 0)
          .fold(0.0, (sum, result) => sum + result.amount.abs());

  double realisedResultToday(PropFirmPortfolio portfolio, DateTime now) =>
      resultsForTradingDay(
        portfolio,
        now,
      ).fold(0.0, (sum, result) => sum + result.amount);

  double rResultToday(PropFirmPortfolio portfolio, DateTime now) =>
      resultsForTradingDay(
        portfolio,
        now,
      ).fold(0.0, (sum, result) => sum + result.rMultiple);

  double activePlannedRisk(PropFirmPortfolio portfolio) => portfolio.openPlans
      .fold(0.0, (sum, trade) => sum + math.max(0, trade.riskAmount));

  int consecutivePortfolioLosses(PropFirmPortfolio portfolio) {
    final sorted = [...portfolio.results]
      ..sort((left, right) => right.resultTime.compareTo(left.resultTime));
    var losses = 0;
    for (final result in sorted) {
      if (result.isLoss) {
        losses++;
      } else if (result.isWin) {
        break;
      }
    }
    return losses;
  }

  bool hasReachedGlobalStop(PropFirmPortfolio portfolio, DateTime now) {
    final policy = portfolio.riskPolicy;
    final dailyResults = resultsForTradingDay(portfolio, now);
    final realisedLoss = realisedLossToday(portfolio, now);
    final openRisk = activePlannedRisk(portfolio);
    return portfolio.stopReason != null &&
            portfolio.stopReason!.code != SessionStopReasonCode.maximumTrades &&
            portfolio.portfolioLocked ||
        portfolio.emotionalStop && policy.stopAfterEmotionalEvent ||
        policy.maximumDailyLoss > 0 &&
            realisedLoss + openRisk >= policy.maximumDailyLoss ||
        policy.maximumSessionLossR > 0 &&
            -rResultToday(portfolio, now) >= policy.maximumSessionLossR ||
        policy.stopAfterOneLosingTrade &&
            dailyResults.any((result) => result.isLoss) ||
        policy.stopAfterOneWinningTrade &&
            dailyResults.any((result) => result.isWin) ||
        policy.stopAfterConsecutiveLosses &&
            consecutivePortfolioLosses(portfolio) >=
                policy.maximumConsecutiveLosses ||
        policy.stopAfterDailyTarget &&
            (policy.combinedSessionProfitTarget > 0 &&
                    realisedResultToday(portfolio, now) >=
                        policy.combinedSessionProfitTarget ||
                portfolio.accounts.any(
                  (account) => account.dailyTargetReached,
                ));
  }

  double disciplineScore(PropFirmPortfolio portfolio) {
    if (portfolio.results.isEmpty && portfolio.disciplineRecords.isEmpty) {
      return double.nan;
    }
    final resultDeductions = portfolio.results.fold<double>(0, (sum, result) {
      return sum +
          (result.followedPlan ? 0 : 8) +
          (result.followedRotation ? 0 : 12);
    });
    final overrideDeductions =
        portfolio.disciplineRecords
            .where((record) => record.type == 'override')
            .length *
        10;
    return (100 - resultDeductions - overrideDeductions).clamp(0, 100);
  }
}
