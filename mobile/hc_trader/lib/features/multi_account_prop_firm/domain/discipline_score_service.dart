import 'prop_firm_models.dart';

class DisciplineScoreResult {
  const DisciplineScoreResult({required this.score, required this.reasons});

  final double? score;
  final List<String> reasons;

  bool get hasEnoughData => score != null;
}

class DisciplineScoreService {
  const DisciplineScoreService();

  DisciplineScoreResult calculate(PropFirmPortfolio portfolio) {
    final meaningfulRecords = portfolio.disciplineRecords.where(
      (record) => const {
        'override',
        'sequence_order_override',
        'sequence_account_override',
        'stop_respected',
        'account_sequence_skip',
      }.contains(record.type),
    );
    if (portfolio.results.isEmpty && meaningfulRecords.isEmpty) {
      return const DisciplineScoreResult(score: null, reasons: []);
    }

    var score = 100.0;
    final reasons = <String>[];
    for (final result in portfolio.results) {
      if (!result.followedPlan) {
        score -= 12;
        reasons.add('discipline.reason.planNotFollowed');
      }
      if (!result.followedRotation) {
        score -= 14;
        reasons.add('discipline.reason.sequenceNotFollowed');
      }
    }
    for (final record in meaningfulRecords) {
      switch (record.type) {
        case 'override':
          score -= record.overrideReason.trim().length >= 5 ? 6 : 12;
          reasons.add('discipline.reason.overrideUsed');
          continue;
        case 'sequence_order_override':
        case 'sequence_account_override':
          score -= 5;
          reasons.add('discipline.reason.sequenceOverride');
          continue;
        case 'stop_respected':
          reasons.add('discipline.reason.stopRespected');
          continue;
        case 'account_sequence_skip':
          reasons.add('discipline.reason.accountSkipped');
          continue;
      }
    }
    return DisciplineScoreResult(
      score: score.clamp(0, 100),
      reasons: List.unmodifiable(reasons.toSet()),
    );
  }

  DisciplineAnalyticsSummary analytics(PropFirmPortfolio portfolio) {
    final results = portfolio.results;
    final plannedTrades = portfolio.plannedTrades;
    final riskTotal = plannedTrades.fold<double>(
      0,
      (sum, trade) => sum + trade.riskAmount,
    );
    var maximumRisk = 0.0;
    for (final trade in plannedTrades) {
      if (trade.riskAmount > maximumRisk) maximumRisk = trade.riskAmount;
    }
    return DisciplineAnalyticsSummary(
      rotationCompliance: results.isEmpty
          ? null
          : results.where((result) => result.followedRotation).length /
                results.length *
                100,
      planCompliance: results.isEmpty
          ? null
          : results.where((result) => result.followedPlan).length /
                results.length *
                100,
      manualOverrides: portfolio.disciplineRecords
          .where(
            (record) => const {
              'override',
              'sequence_order_override',
              'sequence_account_override',
            }.contains(record.type),
          )
          .length,
      respectedStops: portfolio.disciplineRecords
          .where((record) => record.type == 'stop_respected')
          .length,
      avoidedOvertrades: portfolio.disciplineRecords
          .where((record) => record.type == 'overtrade_prevented')
          .length,
      averageRisk: plannedTrades.isEmpty ? 0 : riskTotal / plannedTrades.length,
      maximumRisk: maximumRisk,
    );
  }
}

class DisciplineAnalyticsSummary {
  const DisciplineAnalyticsSummary({
    required this.rotationCompliance,
    required this.planCompliance,
    required this.manualOverrides,
    required this.respectedStops,
    required this.avoidedOvertrades,
    required this.averageRisk,
    required this.maximumRisk,
  });

  final double? rotationCompliance;
  final double? planCompliance;
  final int manualOverrides;
  final int respectedStops;
  final int avoidedOvertrades;
  final double averageRisk;
  final double maximumRisk;
}
