import 'account_daily_trade_limit_service.dart';
import 'account_health_decision_service.dart';
import 'discipline_score_service.dart';
import 'prop_firm_calculation_service.dart';
import 'prop_firm_models.dart';
import 'session_stop_condition_service.dart';

class PortfolioDashboardSnapshot {
  const PortfolioDashboardSnapshot({
    required this.totalAccounts,
    required this.eligibleAccounts,
    required this.protectedAccounts,
    required this.pausedAccounts,
    required this.lockedAccounts,
    required this.breachedAccounts,
    required this.tradesToday,
    required this.realisedResultToday,
    required this.floatingResultToday,
    required this.resultR,
    required this.consecutiveLosses,
    required this.consecutiveWins,
    required this.combinedExposure,
    required this.lossesRemaining,
    required this.tradesRemaining,
    required this.accountsAvailableToday,
    required this.dailyLimitsReached,
    required this.riskBlockedAccounts,
    required this.session,
    required this.discipline,
    required this.disciplineAnalytics,
    required this.portfolioSurvivalDays,
  });

  final int totalAccounts;
  final int eligibleAccounts;
  final int protectedAccounts;
  final int pausedAccounts;
  final int lockedAccounts;
  final int breachedAccounts;
  final int tradesToday;
  final double realisedResultToday;
  final double floatingResultToday;
  final double resultR;
  final int consecutiveLosses;
  final int consecutiveWins;
  final double combinedExposure;
  final int lossesRemaining;
  final int tradesRemaining;
  final int accountsAvailableToday;
  final int dailyLimitsReached;
  final int riskBlockedAccounts;
  final SessionStatusEvaluation session;
  final DisciplineScoreResult discipline;
  final DisciplineAnalyticsSummary disciplineAnalytics;
  final int portfolioSurvivalDays;
}

class PortfolioDashboardService {
  const PortfolioDashboardService({
    this.calculations = const PropFirmCalculationService(),
    this.health = const AccountHealthService(),
    this.stops = const SessionStopConditionService(),
    this.discipline = const DisciplineScoreService(),
    this.dailyTradeLimits = const AccountDailyTradeLimitService(),
  });

  final PropFirmCalculationService calculations;
  final AccountHealthService health;
  final SessionStopConditionService stops;
  final DisciplineScoreService discipline;
  final AccountDailyTradeLimitService dailyTradeLimits;

  PortfolioDashboardSnapshot calculate(
    PropFirmPortfolio portfolio, {
    required DateTime now,
  }) {
    final assessments = {
      for (final account in portfolio.activeAccounts)
        account.id: health.assess(account, now: now),
    };
    final statuses = assessments.values.map((item) => item.status).toList();
    final limits = {
      for (final account in portfolio.activeAccounts)
        account.id: dailyTradeLimits.evaluate(portfolio, account, now: now),
    };
    final countedTrades = limits.values.fold<int>(
      0,
      (total, result) => total + result.currentTrades,
    );
    var consecutiveWins = 0;
    final sorted = [...portfolio.results]
      ..sort((left, right) => right.resultTime.compareTo(left.resultTime));
    for (final result in sorted) {
      if (result.isWin) {
        consecutiveWins++;
      } else if (result.isLoss) {
        break;
      }
    }
    return PortfolioDashboardSnapshot(
      totalAccounts: portfolio.activeAccounts.length,
      eligibleAccounts: assessments.values
          .where((item) => !item.isRestricted)
          .length,
      protectedAccounts: statuses
          .where(
            (status) => {
              AccountHealthStatus.payoutProtection,
              AccountHealthStatus.payoutEligible,
            }.contains(status),
          )
          .length,
      pausedAccounts: statuses
          .where((status) => status == AccountHealthStatus.paused)
          .length,
      lockedAccounts: statuses
          .where(
            (status) => {
              AccountHealthStatus.dailyLocked,
              AccountHealthStatus.critical,
            }.contains(status),
          )
          .length,
      breachedAccounts: statuses
          .where((status) => status == AccountHealthStatus.breached)
          .length,
      tradesToday: countedTrades,
      realisedResultToday: calculations.realisedResultToday(portfolio, now),
      floatingResultToday: portfolio.activeAccounts.fold(
        0.0,
        (sum, account) =>
            sum + (account.situation.todayUnrealisedProfitLoss ?? 0),
      ),
      resultR: calculations.rResultToday(portfolio, now),
      consecutiveLosses: calculations.consecutivePortfolioLosses(portfolio),
      consecutiveWins: consecutiveWins,
      combinedExposure: portfolio.activeAccounts.fold(
        calculations.activePlannedRisk(portfolio),
        (sum, account) => sum + account.currentRiskExposureAmount,
      ),
      lossesRemaining:
          (portfolio.riskPolicy.maximumConsecutiveLosses -
                  calculations.consecutivePortfolioLosses(portfolio))
              .clamp(0, 99),
      tradesRemaining: limits.values.fold<int>(
        0,
        (total, result) => total + (result.remainingTrades ?? 0),
      ),
      accountsAvailableToday: portfolio.activeAccounts
          .where(
            (account) =>
                limits[account.id]!.canStartTrading &&
                assessments[account.id]?.isRestricted != true,
          )
          .length,
      dailyLimitsReached: limits.values
          .where((result) => result.hasReachedLimit)
          .length,
      riskBlockedAccounts: assessments.values
          .where((assessment) => assessment.isRestricted)
          .length,
      session: stops.evaluate(portfolio, now: now),
      discipline: discipline.calculate(portfolio),
      disciplineAnalytics: discipline.analytics(portfolio),
      portfolioSurvivalDays:
          now.difference(portfolio.createdAt).inDays.clamp(0, 999999) + 1,
    );
  }
}
