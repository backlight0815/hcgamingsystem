import 'package:cloud_firestore/cloud_firestore.dart';

enum LeaderboardCategory {
  returnPercentage,
  netProfit,
  riskAdjustedReturn,
  lowestDrawdown,
  disciplineScore,
  planCompliance,
  weeklyPerformance,
  monthlyPerformance,
  consistency,
  propFirmProgress,
  signalProviderPerformance;

  String get storageValue => switch (this) {
    LeaderboardCategory.returnPercentage => 'return_percentage',
    LeaderboardCategory.netProfit => 'net_profit',
    LeaderboardCategory.riskAdjustedReturn => 'risk_adjusted_return',
    LeaderboardCategory.lowestDrawdown => 'lowest_drawdown',
    LeaderboardCategory.disciplineScore => 'discipline_score',
    LeaderboardCategory.planCompliance => 'plan_compliance',
    LeaderboardCategory.weeklyPerformance => 'weekly_performance',
    LeaderboardCategory.monthlyPerformance => 'monthly_performance',
    LeaderboardCategory.consistency => 'consistency',
    LeaderboardCategory.propFirmProgress => 'prop_firm_progress',
    LeaderboardCategory.signalProviderPerformance =>
      'signal_provider_performance',
  };
}

enum LeaderboardPeriodType {
  daily,
  weekly,
  monthly,
  quarterly,
  allTime,
  custom,
}

class LeaderboardPrivacy {
  const LeaderboardPrivacy({
    this.participate = false,
    this.hideRealName = true,
    this.nickname = '',
    this.hideExactBalance = true,
    this.percentageOnly = true,
    this.hideFromPublic = true,
  });

  final bool participate;
  final bool hideRealName;
  final String nickname;
  final bool hideExactBalance;
  final bool percentageOnly;
  final bool hideFromPublic;

  factory LeaderboardPrivacy.fromMap(Map<String, dynamic>? data) =>
      LeaderboardPrivacy(
        participate: data?['participate'] == true,
        hideRealName: data?['hide_real_name'] != false,
        nickname: '${data?['nickname'] ?? ''}'.trim(),
        hideExactBalance: data?['hide_exact_balance'] != false,
        percentageOnly: data?['percentage_only'] != false,
        hideFromPublic: data?['hide_from_public'] != false,
      );

  Map<String, Object?> toMap() => {
    'participate': participate,
    'hide_real_name': hideRealName,
    'nickname': nickname.trim(),
    'hide_exact_balance': hideExactBalance,
    'percentage_only': percentageOnly,
    'hide_from_public': hideFromPublic,
  };
}

class LeaderboardEntry {
  const LeaderboardEntry({
    required this.userId,
    required this.rank,
    required this.displayName,
    required this.score,
    required this.returnPercentage,
    required this.netTradingProfit,
    required this.maxDrawdownPercentage,
    required this.disciplineScore,
    required this.tradeCount,
    required this.tradingDays,
    this.previousRank,
    this.avatarUrl = '',
    this.badge = '',
    this.balanceHidden = true,
    this.verified = false,
  });

  final String userId;
  final int rank;
  final int? previousRank;
  final String displayName;
  final String avatarUrl;
  final double score;
  final double returnPercentage;
  final double netTradingProfit;
  final double maxDrawdownPercentage;
  final double disciplineScore;
  final int tradeCount;
  final int tradingDays;
  final String badge;
  final bool balanceHidden;
  final bool verified;

  int get rankChange => previousRank == null ? 0 : previousRank! - rank;

  factory LeaderboardEntry.fromMap(String id, Map<String, dynamic> data) =>
      LeaderboardEntry(
        userId: '${data['user_id'] ?? id}',
        rank: _int(data['rank']),
        previousRank: data['previous_rank'] == null
            ? null
            : _int(data['previous_rank']),
        displayName: '${data['display_name'] ?? 'Trader'}',
        avatarUrl: '${data['avatar_url'] ?? ''}',
        score: _double(data['score']),
        returnPercentage: _double(data['return_percentage']),
        netTradingProfit: _double(data['net_trading_profit']),
        maxDrawdownPercentage: _double(data['max_drawdown_percentage']),
        disciplineScore: _double(data['discipline_score']),
        tradeCount: _int(data['trade_count']),
        tradingDays: _int(data['trading_days']),
        badge: '${data['badge'] ?? ''}',
        balanceHidden: data['balance_hidden'] != false,
        verified: data['verified'] == true,
      );
}

class LeaderboardPeriod {
  const LeaderboardPeriod({
    required this.id,
    required this.category,
    required this.periodType,
    required this.startsAt,
    required this.endsAt,
    this.status = 'active',
    this.minimumTrades = 10,
    this.minimumTradingDays = 5,
    this.formulaVersion = 'risk-adjusted-v1',
  });

  final String id;
  final LeaderboardCategory category;
  final LeaderboardPeriodType periodType;
  final DateTime startsAt;
  final DateTime endsAt;
  final String status;
  final int minimumTrades;
  final int minimumTradingDays;
  final String formulaVersion;

  factory LeaderboardPeriod.fromMap(String id, Map<String, dynamic> data) =>
      LeaderboardPeriod(
        id: id,
        category: LeaderboardCategory.values.firstWhere(
          (category) => category.storageValue == data['category'],
          orElse: () => LeaderboardCategory.riskAdjustedReturn,
        ),
        periodType: LeaderboardPeriodType.values.firstWhere(
          (type) =>
              type.name.toLowerCase() ==
              '${data['period_type'] ?? ''}'.replaceAll('_', '').toLowerCase(),
          orElse: () => LeaderboardPeriodType.monthly,
        ),
        startsAt: _date(data['starts_at']) ?? DateTime(1970),
        endsAt: _date(data['ends_at']) ?? DateTime(2100),
        status: '${data['status'] ?? 'active'}',
        minimumTrades: _int(data['minimum_trades']),
        minimumTradingDays: _int(data['minimum_trading_days']),
        formulaVersion: '${data['formula_version'] ?? 'risk-adjusted-v1'}',
      );
}

double calculateRiskAdjustedLeaderboardScore({
  required double returnPercentage,
  required double maxDrawdownPercentage,
  required double disciplineScore,
  required double consistencyScore,
  required int tradeCount,
  required int tradingDays,
  int minimumTrades = 10,
  int minimumTradingDays = 5,
  bool verified = true,
  bool suspicious = false,
}) {
  if (!verified ||
      suspicious ||
      tradeCount < minimumTrades ||
      tradingDays < minimumTradingDays) {
    return 0;
  }
  final drawdownPenalty = maxDrawdownPercentage.abs() * 1.5;
  final raw =
      returnPercentage.clamp(-100, 200) * .45 +
      disciplineScore.clamp(0, 100) * .30 +
      consistencyScore.clamp(0, 100) * .25 -
      drawdownPenalty;
  return raw.clamp(0, 100).toDouble();
}

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
double _double(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
DateTime? _date(Object? value) {
  if (value is Timestamp) return value.toDate();
  if (value is DateTime) return value;
  return value == null ? null : DateTime.tryParse('$value');
}
