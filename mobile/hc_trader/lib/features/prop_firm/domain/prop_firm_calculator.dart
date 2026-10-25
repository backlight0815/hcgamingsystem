import '../../../models/mt5_sync_models.dart';
import '../../../models/prop_firm_challenge.dart';
import '../../../services/mt5_trade_eligibility_service.dart';
import '../../../services/trading_data_source_service.dart';

class PropFirmCalculator {
  const PropFirmCalculator();

  static const _eligibility = Mt5TradeEligibilityService();

  PropFirmMetrics evaluate({
    required PropFirmChallenge challenge,
    required Mt5Account? account,
    required List<Mt5TradeRecord> records,
    DateTime? now,
  }) {
    final clock = now ?? DateTime.now();
    final dataset = _eligibility.build(
      records,
      userId: challenge.userId,
      accountKey: challenge.mt5AccountKey,
    );
    final closedRecords = _closedRecords(
      dataset.executedClosedTrades,
      challenge,
    );
    final openPositions = dataset.executedOpenTrades;
    final closedProfit = _round(
      closedRecords.fold<double>(0, (sum, record) => sum + record.netProfit),
    );
    final floatingProfit = _round(
      openPositions.fold<double>(0, (sum, record) => sum + record.netProfit),
    );
    final totalCommission = _round(
      [
        ...closedRecords,
        ...openPositions,
      ].fold<double>(0, (sum, record) => sum + record.commission),
    );
    final totalSwap = _round(
      [
        ...closedRecords,
        ...openPositions,
      ].fold<double>(0, (sum, record) => sum + record.swap),
    );
    final totalFee = _round(
      [
        ...closedRecords,
        ...openPositions,
      ].fold<double>(0, (sum, record) => sum + record.fee),
    );
    final currentBalance = account == null
        ? challenge.startingBalance + closedProfit
        : mt5AccountCapitalBalance(account);
    final currentEquity = account?.equity ?? currentBalance + floatingProfit;
    // Challenge performance is derived only from executed trading activity.
    // Broker balance/equity can include deposits, withdrawals, credits and
    // corrections, so using its delta would misclassify cash flows as profit.
    final accountProfit = _round(closedProfit + floatingProfit);
    final tradingBalance = _round(challenge.startingBalance + closedProfit);
    final tradingEquity = _round(tradingBalance + floatingProfit);
    final accountProfitPercent = _percent(
      accountProfit,
      challenge.startingBalance,
    );

    final targetAmount = _round(
      challenge.startingBalance * challenge.activeProfitTargetPercent / 100,
    );
    final targetProgress = targetAmount <= 0
        ? 100.0
        : (accountProfit / targetAmount * 100).clamp(0, 100).toDouble();
    final remainingTarget = _round(
      (targetAmount - accountProfit).clamp(0, double.infinity),
    );
    final targetReached = accountProfit >= targetAmount;

    final dayKey = _resetDay(clock, challenge.timezoneForDailyReset);
    final todayClosed = _round(
      closedRecords
          .where(
            (record) =>
                _resetDay(
                  record.closeTime ?? record.openTime ?? clock,
                  challenge.timezoneForDailyReset,
                ) ==
                dayKey,
          )
          .fold<double>(0, (sum, record) => sum + record.netProfit),
    );
    final todayFloating = floatingProfit;
    final todayTotal = _round(todayClosed + todayFloating);
    final dailyStartBalance = _round(tradingBalance - todayClosed);
    final dailyStartEquity = _round(tradingEquity - todayTotal);
    final dailyBase = switch (challenge.dailyLossCalculationType) {
      'equity_based' => dailyStartEquity,
      'balance_or_equity' =>
        dailyStartBalance < dailyStartEquity
            ? dailyStartBalance
            : dailyStartEquity,
      _ => dailyStartBalance,
    };
    final dailyLimit = _round(
      dailyBase * challenge.dailyLossLimitPercent / 100,
    );
    final dailyUsed = _round(todayTotal < 0 ? todayTotal.abs() : 0);
    final dailyRemaining = _round(
      (dailyLimit - dailyUsed).clamp(0, double.infinity),
    );
    final dailyUsedPercent = _percent(dailyUsed, dailyLimit);
    final dailyBreach = dailyLimit > 0 && dailyUsed >= dailyLimit;
    final dailyWarning =
        !dailyBreach && dailyLimit > 0 && dailyUsed >= dailyLimit * .8;

    final equityCurve = _equityCurve(
      challenge.startingBalance,
      closedRecords,
      tradingEquity,
    );
    final peak = equityCurve.fold<double>(
      challenge.startingBalance,
      (value, item) => item > value ? item : value,
    );
    final maxLimit = _round(
      challenge.startingBalance * challenge.maximumLossLimitPercent / 100,
    );
    final drawdownBaseline = switch (challenge.maximumLossCalculationType) {
      'trailing_equity' || 'trailing_balance' => peak,
      _ => challenge.startingBalance,
    };
    final minimumAllowed = _round(drawdownBaseline - maxLimit);
    final drawdown = _round(
      (drawdownBaseline - tradingEquity).clamp(0, double.infinity),
    );
    final maxRemaining = _round(
      (maxLimit - drawdown).clamp(0, double.infinity),
    );
    final maxUsedPercent = _percent(drawdown, maxLimit);
    final maxBreach = maxLimit > 0 && drawdown >= maxLimit;
    final maxWarning =
        !maxBreach && maxLimit > 0 && maxRemaining <= maxLimit * .2;

    final tradingDays = closedRecords
        .map(
          (record) => _resetDay(
            record.closeTime ?? record.openTime ?? clock,
            challenge.timezoneForDailyReset,
          ),
        )
        .toSet()
        .length;
    final remainingDays = (challenge.activeMinimumTradingDays - tradingDays)
        .clamp(0, 999);
    final minimumDaysSatisfied =
        tradingDays >= challenge.activeMinimumTradingDays;

    final failed = dailyBreach || maxBreach || challenge.status == 'failed';
    final phase1Passed =
        !failed &&
        challenge.currentPhase == 'phase_1' &&
        targetReached &&
        minimumDaysSatisfied;
    final phase2Passed =
        !failed &&
        challenge.currentPhase == 'phase_2' &&
        targetReached &&
        minimumDaysSatisfied;
    final phase1InProgress =
        !failed && challenge.currentPhase == 'phase_1' && !phase1Passed;
    final phase2InProgress =
        !failed && challenge.currentPhase == 'phase_2' && !phase2Passed;
    final funded = challenge.currentPhase == 'funded';
    final riskStatus = _riskStatus(
      failed: failed,
      phasePassed: phase1Passed || phase2Passed || funded,
      dailyWarning: dailyWarning,
      maxWarning: maxWarning,
      dailyUsedPercent: dailyUsedPercent,
      maxRemaining: maxRemaining,
      maxLimit: maxLimit,
    );

    return PropFirmMetrics(
      challengeId: challenge.id,
      userId: challenge.userId,
      mt5AccountKey: challenge.mt5AccountKey,
      currentPhase: challenge.currentPhase,
      riskStatus: riskStatus,
      startingBalance: _round(challenge.startingBalance),
      currentBalance: _round(currentBalance),
      currentEquity: _round(currentEquity),
      accountProfit: accountProfit,
      accountProfitPercent: accountProfitPercent,
      floatingProfitLoss: floatingProfit,
      closedProfitLoss: closedProfit,
      totalCommission: totalCommission,
      totalFee: totalFee,
      totalSwap: totalSwap,
      phaseProfitTargetAmount: targetAmount,
      currentProfitAmount: accountProfit,
      currentProfitPercent: accountProfitPercent,
      remainingProfitRequired: remainingTarget,
      profitTargetProgressPercent: _round(targetProgress),
      targetReached: targetReached,
      dailyStartBalance: dailyStartBalance,
      dailyStartEquity: dailyStartEquity,
      todayClosedProfitLoss: todayClosed,
      todayFloatingProfitLoss: todayFloating,
      todayTotalProfitLoss: todayTotal,
      dailyLossLimitAmount: dailyLimit,
      dailyLossUsedAmount: dailyUsed,
      dailyLossRemainingAmount: dailyRemaining,
      dailyLossUsedPercent: dailyUsedPercent,
      dailyLossBreach: dailyBreach,
      dailyLossWarning: dailyWarning,
      maximumLossLimitAmount: maxLimit,
      minimumAllowedBalanceEquity: minimumAllowed,
      currentDrawdownAmount: drawdown,
      maximumLossRemainingAmount: maxRemaining,
      maximumLossUsedPercent: maxUsedPercent,
      maximumLossBreach: maxBreach,
      maximumLossWarning: maxWarning,
      tradingDaysCount: tradingDays,
      phaseMinimumTradingDaysRequired: challenge.activeMinimumTradingDays,
      remainingTradingDays: remainingDays,
      minimumDaysSatisfied: minimumDaysSatisfied,
      phase1InProgress: phase1InProgress,
      phase1Passed: phase1Passed,
      phase2InProgress: phase2InProgress,
      phase2Passed: phase2Passed,
      funded: funded,
      failed: failed,
      warning: riskStatus == 'warning' || riskStatus == 'near_breach',
      updatedAt: clock,
    );
  }

  List<Mt5TradeRecord> _closedRecords(
    List<Mt5TradeRecord> records,
    PropFirmChallenge challenge,
  ) {
    final start = challenge.challengeStartDate;
    return records
        .where((record) {
          final time = record.closeTime ?? record.openTime;
          if (start != null && time != null && time.isBefore(start)) {
            return false;
          }
          return true;
        })
        .toList(growable: false);
  }

  List<double> _equityCurve(
    double startingBalance,
    List<Mt5TradeRecord> records,
    double currentEquity,
  ) {
    final sorted = [...records]
      ..sort((left, right) {
        final leftTime = left.closeTime ?? left.openTime ?? DateTime(1970);
        final rightTime = right.closeTime ?? right.openTime ?? DateTime(1970);
        return leftTime.compareTo(rightTime);
      });
    var equity = startingBalance;
    final curve = <double>[startingBalance];
    for (final record in sorted) {
      equity += record.netProfit;
      curve.add(equity);
    }
    curve.add(currentEquity);
    return curve;
  }

  String _riskStatus({
    required bool failed,
    required bool phasePassed,
    required bool dailyWarning,
    required bool maxWarning,
    required double dailyUsedPercent,
    required double maxRemaining,
    required double maxLimit,
  }) {
    if (failed) return 'breached';
    if (phasePassed) return 'passed';
    if (dailyUsedPercent >= 90 ||
        (maxLimit > 0 && maxRemaining <= maxLimit * .1)) {
      return 'near_breach';
    }
    if (dailyWarning || maxWarning) return 'warning';
    return 'healthy';
  }

  String _resetDay(DateTime value, String timezone) {
    final shifted = value.toUtc().add(
      Duration(hours: _timezoneOffset(timezone)),
    );
    return '${shifted.year.toString().padLeft(4, '0')}-'
        '${shifted.month.toString().padLeft(2, '0')}-'
        '${shifted.day.toString().padLeft(2, '0')}';
  }

  int _timezoneOffset(String timezone) {
    return switch (timezone) {
      'UTC' => 0,
      'Asia/Singapore' => 8,
      'Asia/Kuala_Lumpur' => 8,
      'America/New_York' => -5,
      'Europe/London' => 0,
      _ => 8,
    };
  }

  double _percent(double value, double base) =>
      base <= 0 ? 0 : _round(value / base * 100);

  double _round(double value) => double.parse(value.toStringAsFixed(2));
}
