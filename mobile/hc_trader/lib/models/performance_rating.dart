import 'package:cloud_firestore/cloud_firestore.dart';

enum PerformanceRatingPeriod { daily, weekly }

class PerformanceRating {
  const PerformanceRating({
    required this.id,
    required this.userId,
    required this.periodType,
    required this.periodStart,
    required this.periodEnd,
    required this.timezone,
    required this.dataSource,
    required this.algorithmVersion,
    required this.tradingResultScore,
    required this.tradingDisciplineScore,
    required this.tradingPlanComplianceScore,
    required this.consistencyScore,
    required this.overallScore,
    required this.ratingBand,
    this.psychologyScore,
    this.accountId,
    this.tradingPlanId,
    this.tradingPlanVersionId,
    this.evaluatedTradeIds = const [],
    this.excludedRecordIds = const [],
    this.dailyRatingIds = const [],
    this.violations = const [],
    this.positiveBehaviours = const [],
    this.missingData = const [],
    this.recommendations = const [],
    this.scoreBreakdown = const {},
    this.calculationHash = '',
    this.calculatedAt,
  });

  final String id;
  final String userId;
  final PerformanceRatingPeriod periodType;
  final DateTime periodStart;
  final DateTime periodEnd;
  final String timezone;
  final String dataSource;
  final String algorithmVersion;
  final double tradingResultScore;
  final double tradingDisciplineScore;
  final double? psychologyScore;
  final double tradingPlanComplianceScore;
  final double consistencyScore;
  final double overallScore;
  final String ratingBand;
  final String? accountId;
  final String? tradingPlanId;
  final String? tradingPlanVersionId;
  final List<String> evaluatedTradeIds;
  final List<String> excludedRecordIds;
  final List<String> dailyRatingIds;
  final List<String> violations;
  final List<String> positiveBehaviours;
  final List<String> missingData;
  final List<String> recommendations;
  final Map<String, double> scoreBreakdown;
  final String calculationHash;
  final DateTime? calculatedAt;

  factory PerformanceRating.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) => PerformanceRating.fromJson({'id': doc.id, ...?doc.data()});

  factory PerformanceRating.fromJson(Map<String, dynamic> json) {
    return PerformanceRating(
      id: (json['id'] ?? '').toString(),
      userId: (json['user_id'] ?? json['userId'] ?? '').toString(),
      periodType: (json['period_type'] ?? json['periodType']) == 'weekly'
          ? PerformanceRatingPeriod.weekly
          : PerformanceRatingPeriod.daily,
      periodStart: _date(json['period_start'] ?? json['periodStart']),
      periodEnd: _date(json['period_end'] ?? json['periodEnd']),
      timezone: (json['timezone'] ?? 'Asia/Singapore').toString(),
      dataSource: (json['data_source'] ?? json['dataSource'] ?? 'manual')
          .toString(),
      accountId: (json['account_id'] ?? json['accountId'])?.toString(),
      tradingPlanId: (json['trading_plan_id'] ?? json['tradingPlanId'])
          ?.toString(),
      tradingPlanVersionId:
          (json['trading_plan_version_id'] ?? json['tradingPlanVersionId'])
              ?.toString(),
      algorithmVersion:
          (json['algorithm_version'] ?? json['algorithmVersion'] ?? '1.0.0')
              .toString(),
      tradingResultScore: _double(
        json['trading_result_score'] ?? json['tradingResultScore'],
      ),
      tradingDisciplineScore: _double(
        json['trading_discipline_score'] ?? json['tradingDisciplineScore'],
      ),
      psychologyScore: _nullableDouble(
        json['psychology_score'] ?? json['psychologyScore'],
      ),
      tradingPlanComplianceScore: _double(
        json['trading_plan_compliance_score'] ??
            json['tradingPlanComplianceScore'],
      ),
      consistencyScore: _double(
        json['consistency_score'] ?? json['consistencyScore'],
      ),
      overallScore: _double(json['overall_score'] ?? json['overallScore']),
      ratingBand: (json['rating_band'] ?? json['ratingBand'] ?? 'high_risk')
          .toString(),
      evaluatedTradeIds: _strings(
        json['evaluated_trade_ids'] ?? json['evaluatedTradeIds'],
      ),
      excludedRecordIds: _strings(
        json['excluded_record_ids'] ?? json['excludedRecordIds'],
      ),
      dailyRatingIds: _strings(
        json['daily_rating_ids'] ?? json['dailyRatingIds'],
      ),
      violations: _strings(json['violations']),
      positiveBehaviours: _strings(
        json['positive_behaviours'] ?? json['positiveBehaviours'],
      ),
      missingData: _strings(json['missing_data'] ?? json['missingData']),
      recommendations: _strings(json['recommendations']),
      scoreBreakdown: _doubleMap(
        json['score_breakdown'] ?? json['scoreBreakdown'],
      ),
      calculationHash:
          (json['calculation_hash'] ?? json['calculationHash'] ?? '')
              .toString(),
      calculatedAt: _nullableDate(
        json['calculated_at'] ?? json['calculatedAt'],
      ),
    );
  }

  Map<String, Object?> toJson() => {
    'id': id,
    'user_id': userId,
    'period_type': periodType.name,
    'period_start': periodStart.toIso8601String(),
    'period_end': periodEnd.toIso8601String(),
    'timezone': timezone,
    'data_source': dataSource,
    'account_id': accountId,
    'trading_plan_id': tradingPlanId,
    'trading_plan_version_id': tradingPlanVersionId,
    'algorithm_version': algorithmVersion,
    'trading_result_score': tradingResultScore,
    'trading_discipline_score': tradingDisciplineScore,
    'psychology_score': psychologyScore,
    'trading_plan_compliance_score': tradingPlanComplianceScore,
    'consistency_score': consistencyScore,
    'overall_score': overallScore,
    'rating_band': ratingBand,
    'evaluated_trade_ids': evaluatedTradeIds,
    'excluded_record_ids': excludedRecordIds,
    'daily_rating_ids': dailyRatingIds,
    'violations': violations,
    'positive_behaviours': positiveBehaviours,
    'missing_data': missingData,
    'recommendations': recommendations,
    'score_breakdown': scoreBreakdown,
    'calculation_hash': calculationHash,
    'calculated_at': calculatedAt?.toIso8601String(),
  };
}

DateTime _date(Object? value) => _nullableDate(value) ?? DateTime(1970);

DateTime? _nullableDate(Object? value) {
  if (value is Timestamp) return value.toDate();
  if (value is DateTime) return value;
  return DateTime.tryParse(value?.toString() ?? '');
}

double _double(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;

double? _nullableDouble(Object? value) => value == null ? null : _double(value);

List<String> _strings(Object? value) => value is Iterable
    ? value.map((item) => item.toString()).toList(growable: false)
    : const [];

Map<String, double> _doubleMap(Object? value) => value is Map
    ? value.map((key, item) => MapEntry(key.toString(), _double(item)))
    : const {};
