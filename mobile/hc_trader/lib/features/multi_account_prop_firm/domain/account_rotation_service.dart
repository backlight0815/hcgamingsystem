import 'dart:math' as math;

import 'account_daily_trade_limit_service.dart';
import 'prop_firm_calculation_service.dart';
import 'prop_firm_models.dart';

class AccountRotationService {
  const AccountRotationService({
    this.calculations = const PropFirmCalculationService(),
    this.dailyTradeLimits = const AccountDailyTradeLimitService(),
  });

  final PropFirmCalculationService calculations;
  final AccountDailyTradeLimitService dailyTradeLimits;

  RecommendationResult recommend(
    PropFirmPortfolio portfolio, {
    required DateTime now,
  }) {
    if (calculations.hasReachedGlobalStop(portfolio, now)) {
      return const RecommendationResult(
        reasons: [
          RecommendationReason(
            'The portfolio stop condition is active. Consider stopping the session.',
          ),
        ],
        exclusions: {},
        shouldStop: true,
      );
    }

    final exclusions = <String, List<RecommendationReason>>{};
    final candidates = <_Candidate>[];
    final openPlans = portfolio.openPlans;
    for (var index = 0; index < portfolio.accounts.length; index++) {
      final account = portfolio.accounts[index];
      final reasons = _hardExclusions(account, portfolio, now);
      if (reasons.isNotEmpty) {
        exclusions[account.id] = reasons;
        continue;
      }

      var score = 0.0;
      final maxBufferRatio = account.maximumLossLimit <= 0
          ? 0
          : calculations.remainingMaximumDrawdown(account) /
                account.maximumLossLimit;
      score += maxBufferRatio.clamp(0, 2) * 35;
      final dailyBufferRatio = account.dailyLossLimit <= 0
          ? 0
          : account.dailyLossLimit / math.max(1, account.dailyLossLimit);
      score += dailyBufferRatio * 15;
      score += switch (account.priority) {
        AccountPriority.high => 20,
        AccountPriority.standard => 10,
        AccountPriority.low => 0,
      };
      score -= account.consecutiveLosses * 8;
      score += math.min(account.consecutiveWins, 2) * 2;
      score += account.lastTradeAt == null
          ? 12
          : math.min(12, now.difference(account.lastTradeAt!).inHours / 4);
      score -= index.toDouble();
      if (account.status == AccountStatus.recovery) score -= 18;
      if (account.isPayoutNear) score -= 22;
      if (openPlans.any((plan) => plan.accountId == account.id)) score -= 10;
      candidates.add(_Candidate(account: account, score: score));
    }

    if (candidates.isEmpty) {
      return RecommendationResult(
        reasons: const [
          RecommendationReason(
            'No account is eligible under the configured safeguards.',
          ),
        ],
        exclusions: exclusions,
        shouldStop: true,
      );
    }
    candidates.sort((left, right) => right.score.compareTo(left.score));
    final selected = candidates.first.account;
    final reasons = <RecommendationReason>[
      RecommendationReason(
        '{account} has the strongest available drawdown buffer and rotation priority.',
        {'account': selected.name},
      ),
      if (selected.lastTradeAt == null)
        const RecommendationReason(
          'This account has not been traded recently.',
        ),
      if (exclusions.isNotEmpty)
        RecommendationReason(
          '{count} other account(s) are unavailable or protected.',
          {'count': exclusions.length},
        ),
    ];
    return RecommendationResult(
      accountId: selected.id,
      reasons: reasons,
      exclusions: exclusions,
      shouldStop: false,
    );
  }

  List<RecommendationReason> _hardExclusions(
    PropFirmAccount account,
    PropFirmPortfolio portfolio,
    DateTime now,
  ) {
    final reasons = <RecommendationReason>[];
    if ({
      AccountStatus.breached,
      AccountStatus.archived,
      AccountStatus.paused,
      AccountStatus.dailyLocked,
      AccountStatus.portfolioLocked,
      AccountStatus.payoutRequested,
    }.contains(account.status)) {
      reasons.add(
        RecommendationReason('{account} is {status}.', {
          'account': account.name,
          'status': _statusLabel(account.status),
        }),
      );
    }
    if ({
          AccountStatus.coolingAfterWin,
          AccountStatus.coolingAfterLoss,
        }.contains(account.status) &&
        (account.cooldownUntil == null ||
            now.isBefore(account.cooldownUntil!))) {
      reasons.add(
        RecommendationReason('{account} is cooling down.', {
          'account': account.name,
        }),
      );
    }
    if (account.accountSize <= 0 ||
        account.startingBalance <= 0 ||
        account.dailyLossLimit <= 0 ||
        account.maximumLossLimit <= 0 ||
        account.maxRiskPerTrade <= 0) {
      reasons.add(
        RecommendationReason(
          '{account} is missing required risk configuration.',
          {'account': account.name},
        ),
      );
    }
    final dailyTradeLimit = dailyTradeLimits.evaluate(
      portfolio,
      account,
      now: now,
    );
    if (dailyTradeLimit.hasReachedLimit) {
      reasons.add(
        RecommendationReason(
          '{account} reached its daily trade limit ({current}/{limit}).',
          {
            'account': account.name,
            'current': dailyTradeLimit.currentTrades,
            'limit': dailyTradeLimit.limit,
          },
        ),
      );
    }
    if (account.maxRiskPerTrade >
        math.min(
          account.dailyLossLimit,
          calculations.remainingMaximumDrawdown(account),
        )) {
      reasons.add(
        RecommendationReason(
          '{account} risk exceeds its remaining configured drawdown room.',
          {'account': account.name},
        ),
      );
    }
    if (portfolio.strategy.protectPayoutReadyAccounts &&
        {
          AccountStatus.payoutProtection,
          AccountStatus.payoutReady,
        }.contains(account.status)) {
      reasons.add(
        RecommendationReason('{account} is protected for payout.', {
          'account': account.name,
        }),
      );
    }
    if (portfolio.strategy.oneAccountPerSession &&
        portfolio.activeAccountId != null &&
        portfolio.activeAccountId != account.id) {
      reasons.add(
        const RecommendationReason(
          'The selected strategy permits one account for this session.',
        ),
      );
    }
    return reasons;
  }

  List<RecommendationReason> duplicateExposureWarnings(
    PropFirmPortfolio portfolio,
    PlannedTrade candidate,
  ) {
    final warnings = <RecommendationReason>[];
    final duplicates = portfolio.openPlans.where(
      (plan) =>
          plan.id != candidate.id &&
          plan.instrument.trim().toUpperCase() ==
              candidate.instrument.trim().toUpperCase(),
    );
    for (final existing in duplicates) {
      warnings.add(
        RecommendationReason(
          existing.direction == candidate.direction
              ? 'The same instrument and direction is already planned on another account.'
              : 'The same instrument is already planned on another account.',
        ),
      );
    }
    final combinedRisk =
        calculations.activePlannedRisk(portfolio) + candidate.riskAmount;
    if (combinedRisk > portfolio.riskPolicy.maximumDailyLoss) {
      warnings.add(
        const RecommendationReason(
          'Combined planned risk exceeds the portfolio daily loss limit.',
        ),
      );
    }
    if (!portfolio.riskPolicy.simultaneousTradingAllowed &&
        portfolio.openPlans.isNotEmpty) {
      warnings.add(
        const RecommendationReason(
          'Simultaneous account trading is disabled in the safeguards.',
        ),
      );
    }
    return warnings;
  }

  PropFirmAccount transitionAfterResult(
    PropFirmAccount account,
    RecordedTradeResult result,
    RotationStrategy strategy,
    PortfolioRiskPolicy policy,
  ) {
    final isWin = result.isWin;
    final isLoss = result.isLoss;
    final status = isWin
        ? AccountStatus.coolingAfterWin
        : isLoss
        ? (strategy.lockAfterLoss
              ? AccountStatus.dailyLocked
              : AccountStatus.coolingAfterLoss)
        : AccountStatus.available;
    final cooldownMinutes = isWin
        ? policy.cooldownAfterWinMinutes
        : isLoss
        ? policy.cooldownAfterLossMinutes
        : 0;
    final nextProfit = account.currentProfit + result.amount;
    final payoutTarget = math.max(account.minimumPayout, account.desiredPayout);
    final payoutReady = payoutTarget > 0 && nextProfit >= payoutTarget;
    return account.copyWith(
      currentBalance: account.currentBalance + result.amount,
      tradesToday: account.tradesToday + 1,
      consecutiveWins: isWin ? account.consecutiveWins + 1 : 0,
      consecutiveLosses: isLoss ? account.consecutiveLosses + 1 : 0,
      lastTradeResult: result.type.name,
      status: payoutReady && strategy.protectPayoutReadyAccounts
          ? AccountStatus.payoutReady
          : status,
      cooldownUntil: cooldownMinutes <= 0
          ? result.resultTime
          : result.resultTime.add(Duration(minutes: cooldownMinutes)),
      lastTradeAt: result.resultTime,
    );
  }
}

String _statusLabel(AccountStatus value) => switch (value) {
  AccountStatus.available => 'Healthy',
  AccountStatus.recommended => 'Recommended',
  AccountStatus.active => 'Active',
  AccountStatus.coolingAfterWin => 'Cooling after win',
  AccountStatus.coolingAfterLoss => 'Cooling after loss',
  AccountStatus.dailyLocked => 'Daily locked',
  AccountStatus.portfolioLocked => 'Portfolio stopped',
  AccountStatus.recovery => 'Recovery',
  AccountStatus.payoutProtection => 'Protected',
  AccountStatus.payoutReady => 'Payout ready',
  AccountStatus.payoutRequested => 'Payout requested',
  AccountStatus.paused => 'Paused',
  AccountStatus.breached => 'Breached',
  AccountStatus.archived => 'Archived',
};

class _Candidate {
  const _Candidate({required this.account, required this.score});

  final PropFirmAccount account;
  final double score;
}
