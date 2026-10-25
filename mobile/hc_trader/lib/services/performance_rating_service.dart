import 'dart:math' as math;

import '../models/performance_rating.dart';
import '../models/standalone_models.dart';
import '../models/trading_data_source.dart';
import '../models/trading_journal_entry.dart';
import 'psychology_score_service.dart';

class PerformanceRatingConfig {
  const PerformanceRatingConfig({
    this.algorithmVersion = 'hc-performance-v1.0.0',
    this.resultWeight = .20,
    this.disciplineWeight = .30,
    this.psychologyWeight = .20,
    this.planComplianceWeight = .30,
    this.missingStopLossPenalty = 30,
    this.riskExceededPenalty = 25,
    this.dailyLossExceededPenalty = 35,
    this.overtradingPenalty = 15,
    this.revengePatternPenalty = 15,
    this.seriousViolationDisciplineCap = 45,
    this.seriousViolationPlanCap = 55,
  });

  final String algorithmVersion;
  final double resultWeight;
  final double disciplineWeight;
  final double psychologyWeight;
  final double planComplianceWeight;
  final double missingStopLossPenalty;
  final double riskExceededPenalty;
  final double dailyLossExceededPenalty;
  final double overtradingPenalty;
  final double revengePatternPenalty;
  final double seriousViolationDisciplineCap;
  final double seriousViolationPlanCap;
}

class PerformanceRatingService {
  const PerformanceRatingService({
    this.config = const PerformanceRatingConfig(),
  });

  final PerformanceRatingConfig config;

  PerformanceRating calculateDaily({
    required String userId,
    required DateTime localDate,
    required int timezoneOffsetMinutes,
    required String timezone,
    required TradingDataDisplayMode dataSource,
    required Iterable<TradingJournalEntry> records,
    required Iterable<TradingPlanRecord> planVersions,
    required Iterable<PsychologyCheckIn> psychologyChecks,
    String? mainMt5AccountId,
  }) {
    final date = DateTime(localDate.year, localDate.month, localDate.day);
    final periodEnd = date.add(const Duration(days: 1));
    final sourceName = dataSource == TradingDataDisplayMode.mt5Only
        ? 'mt5'
        : 'manual';
    final included = <TradingJournalEntry>[];
    final excluded = <String>[];
    final seen = <String>{};
    for (final trade in records) {
      final key = _tradeKey(trade);
      final localOpened = _localTime(trade.openDate, timezoneOffsetMinutes);
      final sourceMatches = dataSource == TradingDataDisplayMode.mt5Only
          ? trade.source == TradingRecordSource.mt5Sync
          : trade.source.isManualDisplaySource;
      final accountMatches =
          dataSource != TradingDataDisplayMode.mt5Only ||
          (mainMt5AccountId ?? '').isEmpty ||
          trade.tradingAccountId == mainMt5AccountId;
      final status = trade.status.trim().toLowerCase();
      final executed =
          trade.type.trim().toLowerCase() == 'trade' &&
          !const {
            'cancelled',
            'canceled',
            'deleted',
            'expired',
            'pending',
            'draft',
          }.contains(status);
      final sameDate =
          localOpened.year == date.year &&
          localOpened.month == date.month &&
          localOpened.day == date.day;
      if (!sourceMatches ||
          !accountMatches ||
          !executed ||
          !sameDate ||
          !seen.add(key)) {
        excluded.add(trade.id);
        continue;
      }
      included.add(trade);
    }
    included.sort((left, right) => left.openDate.compareTo(right.openDate));

    final plan = _planForDate(planVersions, date);
    final psychology = _psychologyForDate(
      psychologyChecks,
      date,
      timezoneOffsetMinutes,
    );
    final violations = <String>[];
    final positives = <String>[];
    final missing = <String>[];
    final recommendations = <String>[];

    final resultScore = _resultScore(included, missing, positives);
    var disciplineScore = 100.0;
    var planScore = 100.0;
    var seriousViolation = false;

    if (plan == null) {
      planScore = 50;
      missing.add('trading_plan_missing');
      recommendations.add('create_active_trading_plan');
    }

    final missingStopLoss = included.any(_knownMissingStopLoss);
    if (missingStopLoss) {
      disciplineScore -= config.missingStopLossPenalty;
      planScore -= config.missingStopLossPenalty;
      violations.add('missing_stop_loss');
      recommendations.add('always_define_stop_loss');
      seriousViolation = true;
    } else if (included.isNotEmpty &&
        included.every((trade) => !_stopLossInformationKnown(trade))) {
      missing.add('stop_loss_data_missing');
    }

    if (plan != null) {
      final rules = plan.plan;
      if (included.length > rules.maxTradesPerDay) {
        disciplineScore -= config.overtradingPenalty;
        planScore -= config.overtradingPenalty;
        violations.add('maximum_trades_exceeded');
        recommendations.add('respect_maximum_trades');
      }
      final capital = rules.effectiveRiskCapital ?? rules.accountCapital;
      if (capital != null && capital > 0) {
        final dailyLoss = included
            .where((trade) => trade.profitLoss < 0)
            .fold<double>(0, (sum, trade) => sum + trade.profitLoss.abs());
        if (dailyLoss / capital * 100 > rules.dailyMaxLossPercent) {
          disciplineScore -= config.dailyLossExceededPenalty;
          planScore -= config.dailyLossExceededPenalty;
          violations.add('daily_loss_limit_exceeded');
          recommendations.add('stop_at_daily_loss_limit');
          seriousViolation = true;
        }
        final oversizedLoss = included.any(
          (trade) =>
              trade.profitLoss < 0 &&
              trade.profitLoss.abs() / capital * 100 > rules.riskPercent,
        );
        if (oversizedLoss) {
          disciplineScore -= config.riskExceededPenalty;
          planScore -= config.riskExceededPenalty;
          violations.add('risk_per_trade_exceeded');
          recommendations.add('reduce_risk_per_trade');
          seriousViolation = true;
        }
      } else if (included.isNotEmpty) {
        missing.add('capital_basis_missing');
      }

      final allowedPairs = rules.allowedPairs
          .map((value) => value.trim().toUpperCase())
          .where((value) => value.isNotEmpty)
          .toSet();
      if (allowedPairs.isNotEmpty &&
          included.any(
            (trade) => !allowedPairs.contains(trade.pair.trim().toUpperCase()),
          )) {
        disciplineScore -= 10;
        planScore -= 15;
        violations.add('instrument_not_allowed');
      }
      final allowedDays = rules.allowedTradingDays
          .map((value) => value.trim().toLowerCase())
          .toSet();
      if (allowedDays.isNotEmpty &&
          !allowedDays.contains(_weekday(date).toLowerCase())) {
        disciplineScore -= 10;
        planScore -= 15;
        violations.add('trading_day_not_allowed');
      }
      for (final trade in included) {
        if (trade.tradingPlanId == null || trade.tradingPlanId!.isEmpty) {
          missing.add('trade_plan_link_missing');
        } else if (!trade.followedTradingPlan) {
          planScore -= 15;
          violations.add('trading_plan_not_followed');
        }
        if (rules.allowedStrategyIds.isNotEmpty) {
          final strategyId = trade.strategyId?.trim() ?? '';
          if (strategyId.isEmpty) {
            missing.add('strategy_link_missing');
          } else if (!rules.allowedStrategyIds.contains(strategyId)) {
            planScore -= 10;
            violations.add('strategy_not_allowed');
          }
        }
        final knownChecks = trade.planComplianceChecklist.values;
        final riskRewardMet =
            trade.planComplianceChecklist['risk_reward_met'] ??
            trade.planComplianceChecklist['riskRewardMet'];
        if (riskRewardMet == false) {
          disciplineScore -= 10;
          planScore -= 15;
          violations.add('risk_reward_below_plan');
        }
        if (knownChecks.isNotEmpty) {
          final failed = knownChecks.where((passed) => !passed).length;
          planScore -= failed * 5;
          if (failed > 0) violations.add('entry_rule_not_confirmed');
        } else {
          missing.add('plan_checklist_incomplete');
        }
      }
    }

    if (_hasPossibleRevengePattern(included, plan?.plan)) {
      disciplineScore -= config.revengePatternPenalty;
      violations.add('possible_revenge_trading');
      recommendations.add('pause_after_a_loss');
    }

    if (seriousViolation) {
      disciplineScore = math.min(
        disciplineScore,
        config.seriousViolationDisciplineCap,
      );
      planScore = math.min(planScore, config.seriousViolationPlanCap);
    }
    disciplineScore = _score(disciplineScore);
    planScore = _score(planScore);
    if (included.isNotEmpty && violations.isEmpty) {
      positives.add('rules_followed_without_detected_violation');
    }

    double? psychologyScore;
    if (psychology == null) {
      missing.add('psychology_check_missing');
      recommendations.add('complete_psychology_check_before_trading');
    } else {
      psychologyScore = const PsychologyScoreService()
          .evaluate(psychology)
          .score;
      final firstTrade = included.isEmpty ? null : included.first.openDate;
      if (firstTrade != null && psychology.createdAt.isAfter(firstTrade)) {
        missing.add('psychology_check_completed_after_trading');
      } else {
        positives.add('psychology_check_completed_before_trading');
      }
    }

    final overall = _overall(
      result: resultScore,
      discipline: disciplineScore,
      psychology: psychologyScore,
      plan: planScore,
    );
    final id =
        'daily:${_dateKey(date)}:$sourceName:${mainMt5AccountId ?? 'none'}';
    final scoreBreakdown = <String, double>{
      'trading_result': resultScore,
      'trading_discipline': disciplineScore,
      'trading_plan_compliance': planScore,
    };
    if (psychologyScore != null) {
      scoreBreakdown['psychology'] = psychologyScore;
    }
    return PerformanceRating(
      id: id,
      userId: userId,
      periodType: PerformanceRatingPeriod.daily,
      periodStart: date,
      periodEnd: periodEnd,
      timezone: timezone,
      dataSource: sourceName,
      accountId: mainMt5AccountId,
      tradingPlanId: plan?.id,
      tradingPlanVersionId: plan?.versionId,
      algorithmVersion: config.algorithmVersion,
      tradingResultScore: resultScore,
      tradingDisciplineScore: disciplineScore,
      psychologyScore: psychologyScore,
      tradingPlanComplianceScore: planScore,
      consistencyScore: 100,
      overallScore: overall,
      ratingBand: ratingBand(overall),
      evaluatedTradeIds: included.map((trade) => trade.id).toList(),
      excludedRecordIds: excluded.toSet().toList(),
      violations: violations.toSet().toList(),
      positiveBehaviours: positives.toSet().toList(),
      missingData: missing.toSet().toList(),
      recommendations: recommendations.toSet().toList(),
      scoreBreakdown: scoreBreakdown,
    );
  }

  PerformanceRating calculateWeekly({
    required String userId,
    required DateTime weekDate,
    required String timezone,
    required String dataSource,
    required Iterable<PerformanceRating> dailyRatings,
    String? accountId,
  }) {
    final start = DateTime(
      weekDate.year,
      weekDate.month,
      weekDate.day - (weekDate.weekday - DateTime.monday),
    );
    final end = start.add(const Duration(days: 7));
    final days =
        dailyRatings
            .where(
              (rating) =>
                  rating.periodType == PerformanceRatingPeriod.daily &&
                  !rating.periodStart.isBefore(start) &&
                  rating.periodStart.isBefore(end) &&
                  rating.dataSource == dataSource &&
                  (accountId == null || rating.accountId == accountId),
            )
            .toList()
          ..sort(
            (left, right) => left.periodStart.compareTo(right.periodStart),
          );
    final result = _average(days.map((day) => day.tradingResultScore), 50);
    final discipline = _average(
      days.map((day) => day.tradingDisciplineScore),
      100,
    );
    final psychologyValues = days
        .map((day) => day.psychologyScore)
        .whereType<double>()
        .toList();
    final psychology = psychologyValues.isEmpty
        ? null
        : _average(psychologyValues, 50);
    final plan = _average(
      days.map((day) => day.tradingPlanComplianceScore),
      100,
    );
    var consistency =
        100 -
        _standardDeviation(days.map((day) => day.tradingDisciplineScore)) -
        _standardDeviation(days.map((day) => day.tradingPlanComplianceScore));
    final violationCounts = <String, int>{};
    for (final day in days) {
      for (final violation in day.violations) {
        violationCounts.update(
          violation,
          (count) => count + 1,
          ifAbsent: () => 1,
        );
      }
    }
    consistency -=
        violationCounts.values
            .where((count) => count > 1)
            .fold<int>(0, (sum, count) => sum + (count - 1)) *
        8;
    if (days.isNotEmpty) {
      consistency -=
          (days.where((day) => day.psychologyScore == null).length /
              days.length) *
          15;
    }
    consistency = _score(consistency);
    var overall = _overall(
      result: result,
      discipline: discipline,
      psychology: psychology,
      plan: plan,
    );
    final serious = days.where(
      (day) => day.violations.any(
        const {
          'missing_stop_loss',
          'daily_loss_limit_exceeded',
          'risk_per_trade_exceeded',
        }.contains,
      ),
    );
    if (serious.isNotEmpty) {
      overall = math.min(
        overall,
        serious.map((day) => day.overallScore).reduce(math.min) + 25,
      );
    }
    final weekId = _isoWeekKey(start);
    final violations = days.expand((day) => day.violations).toSet().toList();
    final positives = days
        .expand((day) => day.positiveBehaviours)
        .toSet()
        .toList();
    final missing = days.expand((day) => day.missingData).toSet().toList();
    final scoreBreakdown = <String, double>{
      'trading_result': result,
      'trading_discipline': discipline,
      'trading_plan_compliance': plan,
      'consistency': consistency,
    };
    if (psychology != null) scoreBreakdown['psychology'] = psychology;
    return PerformanceRating(
      id: 'weekly:$weekId:$dataSource:${accountId ?? 'none'}',
      userId: userId,
      periodType: PerformanceRatingPeriod.weekly,
      periodStart: start,
      periodEnd: end,
      timezone: timezone,
      dataSource: dataSource,
      accountId: accountId,
      tradingPlanId: days.isEmpty ? null : days.last.tradingPlanId,
      tradingPlanVersionId: days.isEmpty
          ? null
          : days.last.tradingPlanVersionId,
      algorithmVersion: config.algorithmVersion,
      tradingResultScore: result,
      tradingDisciplineScore: discipline,
      psychologyScore: psychology,
      tradingPlanComplianceScore: plan,
      consistencyScore: consistency,
      overallScore: _score(overall),
      ratingBand: ratingBand(overall),
      dailyRatingIds: days.map((day) => day.id).toList(),
      evaluatedTradeIds: days
          .expand((day) => day.evaluatedTradeIds)
          .toSet()
          .toList(),
      excludedRecordIds: days
          .expand((day) => day.excludedRecordIds)
          .toSet()
          .toList(),
      violations: violations,
      positiveBehaviours: positives,
      missingData: missing,
      recommendations: days
          .expand((day) => day.recommendations)
          .toSet()
          .toList(),
      scoreBreakdown: scoreBreakdown,
    );
  }

  String ratingBand(double score) => switch (score) {
    >= 90 => 'excellent',
    >= 75 => 'good',
    >= 60 => 'developing',
    >= 40 => 'needs_attention',
    _ => 'high_risk',
  };

  double _resultScore(
    List<TradingJournalEntry> trades,
    List<String> missing,
    List<String> positives,
  ) {
    final closed = trades.where((trade) => trade.status == 'closed').toList();
    if (closed.isEmpty) {
      missing.add(trades.isEmpty ? 'no_trades_for_period' : 'no_closed_trades');
      return 50;
    }
    final wins = closed.where((trade) => trade.profitLoss > 0).toList();
    final losses = closed.where((trade) => trade.profitLoss < 0).toList();
    final grossProfit = wins.fold<double>(
      0,
      (sum, trade) => sum + trade.profitLoss,
    );
    final grossLoss = losses.fold<double>(
      0,
      (sum, trade) => sum + trade.profitLoss.abs(),
    );
    final net = grossProfit - grossLoss;
    final decisive = wins.length + losses.length;
    final winRate = decisive == 0 ? 50.0 : wins.length / decisive * 100;
    final profitFactor = grossLoss == 0
        ? (grossProfit > 0 ? 3.0 : 1.0)
        : grossProfit / grossLoss;
    var score = 50.0;
    score += net > 0
        ? 15
        : net < 0
        ? -15
        : 0;
    score += ((winRate - 50) * .3).clamp(-15, 15);
    score += ((profitFactor - 1) * 10).clamp(-15, 15);
    if (net > 0) positives.add('positive_realised_result');
    return _score(score.clamp(5, 95).toDouble());
  }

  bool _hasPossibleRevengePattern(
    List<TradingJournalEntry> trades,
    TradingPlan? plan,
  ) {
    if (trades.length < 2) return false;
    final cooldown = plan?.cooldownAfterLossMinutes ?? 30;
    for (var index = 1; index < trades.length; index++) {
      final previous = trades[index - 1];
      final current = trades[index];
      if (!previous.isLoss) continue;
      final minutes = current.openDate.difference(previous.closeDate).inMinutes;
      if (minutes >= 0 &&
          minutes <= cooldown &&
          current.lotSize > previous.lotSize * 1.25) {
        return true;
      }
    }
    return false;
  }

  TradingPlanRecord? _planForDate(
    Iterable<TradingPlanRecord> plans,
    DateTime date,
  ) {
    final matches =
        plans.where((record) {
          final starts = record.effectiveStart;
          final ends = record.effectiveTo;
          return record.plan.isActive &&
              !starts.isAfter(date.add(const Duration(days: 1))) &&
              (ends == null || ends.isAfter(date));
        }).toList()..sort(
          (left, right) => right.effectiveStart.compareTo(left.effectiveStart),
        );
    return matches.isEmpty ? null : matches.first;
  }

  PsychologyCheckIn? _psychologyForDate(
    Iterable<PsychologyCheckIn> checks,
    DateTime date,
    int offset,
  ) {
    final matches = checks.where((check) {
      final local = _localTime(check.createdAt, offset);
      return local.year == date.year &&
          local.month == date.month &&
          local.day == date.day;
    }).toList()..sort((a, b) => b.createdAt.compareTo(a.createdAt));
    return matches.isEmpty ? null : matches.first;
  }

  bool _knownMissingStopLoss(TradingJournalEntry trade) {
    final checks = trade.planComplianceChecklist;
    return checks['stop_loss'] == false ||
        checks['stopLoss'] == false ||
        trade.mistakeTags.any(
          (tag) => const {
            'missing_stop_loss',
            'no_stop_loss',
            'without_sl',
          }.contains(tag.trim().toLowerCase()),
        );
  }

  bool _stopLossInformationKnown(TradingJournalEntry trade) =>
      trade.planComplianceChecklist.containsKey('stop_loss') ||
      trade.planComplianceChecklist.containsKey('stopLoss') ||
      trade.followedSlRule ||
      _knownMissingStopLoss(trade);

  double _overall({
    required double result,
    required double discipline,
    required double? psychology,
    required double plan,
  }) {
    var weighted =
        result * config.resultWeight +
        discipline * config.disciplineWeight +
        plan * config.planComplianceWeight;
    var weights =
        config.resultWeight +
        config.disciplineWeight +
        config.planComplianceWeight;
    if (psychology != null) {
      weighted += psychology * config.psychologyWeight;
      weights += config.psychologyWeight;
    }
    return _score(weighted / weights);
  }

  DateTime _localTime(DateTime value, int offsetMinutes) =>
      value.toUtc().add(Duration(minutes: offsetMinutes));

  String _tradeKey(TradingJournalEntry trade) => [
    trade.source.storageValue,
    trade.tradingAccountId ?? '',
    trade.id,
  ].join(':');

  String _weekday(DateTime date) => const [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
  ][date.weekday - 1];

  double _score(double value) =>
      (value.clamp(0, 100).toDouble() * 100).round() / 100;

  double _average(Iterable<double> values, double fallback) {
    final list = values.toList();
    return list.isEmpty
        ? fallback
        : _score(list.reduce((a, b) => a + b) / list.length);
  }

  double _standardDeviation(Iterable<double> values) {
    final list = values.toList();
    if (list.length < 2) return 0;
    final average = list.reduce((a, b) => a + b) / list.length;
    final variance =
        list
            .map((value) => math.pow(value - average, 2).toDouble())
            .reduce((a, b) => a + b) /
        list.length;
    return math.sqrt(variance);
  }

  String _dateKey(DateTime date) =>
      '${date.year.toString().padLeft(4, '0')}-'
      '${date.month.toString().padLeft(2, '0')}-'
      '${date.day.toString().padLeft(2, '0')}';

  String _isoWeekKey(DateTime monday) {
    final thursday = monday.add(const Duration(days: 3));
    final firstThursday = DateTime(thursday.year, 1, 4);
    final firstMonday = firstThursday.subtract(
      Duration(days: firstThursday.weekday - 1),
    );
    final week = thursday.difference(firstMonday).inDays ~/ 7 + 1;
    return '${thursday.year}-W${week.toString().padLeft(2, '0')}';
  }
}
