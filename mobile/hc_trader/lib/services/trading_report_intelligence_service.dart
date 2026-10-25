import 'dart:math' as math;

import '../models/trading_journal_entry.dart';
import '../models/trading_report.dart';

typedef TradingReportIntelligenceResult = ({
  TradingScoreProfile score,
  TradingPositionProfile position,
  TradingBehaviourProfile behaviour,
  TradingTraderLevelProfile level,
  TradingHedgingProfile hedging,
  String performanceStatus,
  String traderStyle,
  String executiveInsight,
  String resumeSummary,
  String technicalSkill,
  String riskProfile,
  String administrationAction,
  List<String> strengths,
  List<String> risks,
  List<String> coachingFocus,
});

/// Ports the report-only score, level, position-size, and behaviour rules from
/// Laravel's TradingStatisticsController, TradingJournalAnalytics, and
/// TraderLevelProfileService. All inputs must already be owner/account scoped.
class TradingReportIntelligenceService {
  const TradingReportIntelligenceService();

  TradingReportIntelligenceResult build({
    required List<TradingJournalEntry> trades,
    required TradingReportSummary summary,
    required List<TradingBreakdownStat> pairStats,
    required List<TradingBreakdownStat> sessionStats,
    required List<TradingRuleMonitorItem> ruleMonitor,
    required double startingCapital,
  }) {
    final position = _positionConsistency(trades);
    final behaviour = _behaviour(trades, summary, position, startingCapital);
    final score = _score(summary, position, behaviour);
    final level = _level(summary, score, position, behaviour);
    final hedging = _hedging(trades);
    final status = _performanceStatus(summary, ruleMonitor);
    final style = _traderStyle(behaviour, position);
    final technicalSkill = score.score >= 85
        ? 'Advanced'
        : score.score >= 70
        ? 'Skilled'
        : score.score >= 50
        ? 'Developing'
        : 'Needs Development';
    final riskProfile =
        behaviour.finalScore >= 65 || summary.maxDrawdownPercent > 10
        ? 'High Risk'
        : behaviour.finalScore >= 40 || position.score < 45
        ? 'Needs Monitoring'
        : 'Controlled';
    final administrationAction = riskProfile == 'High Risk'
        ? 'Hold progression and review behaviour, drawdown, and position sizing.'
        : riskProfile == 'Needs Monitoring'
        ? 'Suitable for normal progression, with routine monitoring.'
        : 'Suitable for normal progression under standard controls.';
    final strengths = _strengths(summary, pairStats, sessionStats, position);
    final risks = _risks(summary, behaviour, position);
    final coaching = _coaching(behaviour, position, summary);
    final insight =
        score.meaning +
        (behaviour.finalScore < 40
            ? ' Behaviour is currently controlled; continue protecting daily and total drawdown.'
            : ' Behaviour risk remains active and should be reduced before scaling exposure.');
    final resume = _resume(summary, score, level, style, riskProfile, position);

    return (
      score: score,
      position: position,
      behaviour: behaviour,
      level: level,
      hedging: hedging,
      performanceStatus: status,
      traderStyle: style,
      executiveInsight: insight,
      resumeSummary: resume,
      technicalSkill: technicalSkill,
      riskProfile: riskProfile,
      administrationAction: administrationAction,
      strengths: strengths,
      risks: risks,
      coachingFocus: coaching,
    );
  }

  TradingPositionProfile _positionConsistency(
    List<TradingJournalEntry> trades,
  ) {
    final lots =
        trades.map((trade) => trade.lotSize).where((lot) => lot > 0).toList()
          ..sort();
    if (lots.isEmpty) {
      return const TradingPositionProfile(
        status: 'No Position Data',
        grade: 'N/A',
        description: 'No lot-size data is available yet.',
        score: 0,
        mainLot: 0,
        mainLotShare: 0,
        nearMainLotShare: 0,
        averageLot: 0,
        medianLot: 0,
        minimumLot: 0,
        maximumLot: 0,
        coefficientOfVariation: 0,
        rangeRatio: 0,
      );
    }

    final count = lots.length;
    final average = lots.reduce((a, b) => a + b) / count;
    final median = count.isOdd
        ? lots[count ~/ 2]
        : (lots[(count ~/ 2) - 1] + lots[count ~/ 2]) / 2;
    final variance = count > 1
        ? lots.fold<double>(0, (sum, lot) => sum + math.pow(lot - average, 2)) /
              count
        : 0.0;
    final deviation = math.sqrt(variance);
    final coefficient = average > 0 ? (deviation / average) * 100 : 0.0;
    final rangeRatio = lots.first > 0 ? lots.last / lots.first : 0.0;
    final frequencies = <String, int>{};
    for (final lot in lots) {
      final key = lot.toStringAsFixed(4);
      frequencies[key] = (frequencies[key] ?? 0) + 1;
    }
    final mode = frequencies.entries.toList()
      ..sort((a, b) {
        final countOrder = b.value.compareTo(a.value);
        return countOrder != 0 ? countOrder : a.key.compareTo(b.key);
      });
    final mainLot = double.parse(mode.first.key);
    final mainCount = mode.first.value;
    final mainShare = (mainCount / count) * 100;
    final tolerance = math.max(.02, mainLot * .35);
    final nearCount = lots
        .where((lot) => (lot - mainLot).abs() <= tolerance)
        .length;
    final nearShare = (nearCount / count) * 100;
    final sampleScore = math.min(10.0, (count / 100) * 10);
    final mainScore = (mainShare / 100) * 35;
    final nearScore = (nearShare / 100) * 20;
    final variationScore = math.max(
      0.0,
      25 - math.min(25.0, coefficient * .45),
    );
    final rangeScore = math.max(
      0.0,
      10 - math.min(10.0, math.max(0.0, rangeRatio - 1) * 3),
    );
    final score = count > 1
        ? _round(
            (sampleScore + mainScore + nearScore + variationScore + rangeScore)
                .clamp(0, 100),
          )
        : 45.0;
    final status = coefficient <= 10 && rangeRatio <= 1.5
        ? 'Very Consistent'
        : coefficient <= 20 && rangeRatio <= 2
        ? 'Consistent'
        : coefficient <= 35 && rangeRatio <= 2.5
        ? 'Moderately Dynamic'
        : coefficient <= 60
        ? 'Dynamic'
        : 'Highly Dynamic';
    final description = score >= 85
        ? 'Strong consistency: most trades stay around the main lot size.'
        : score >= 70
        ? 'Good consistency with a visible main position size.'
        : score >= 55
        ? 'Moderate consistency: position size still changes frequently.'
        : 'Low consistency: position sizes vary widely or the sample is limited.';

    return TradingPositionProfile(
      status: status,
      grade: _positionGrade(score),
      description: description,
      score: score,
      mainLot: _round(mainLot, 4),
      mainLotShare: _round(mainShare),
      nearMainLotShare: _round(nearShare),
      averageLot: _round(average, 4),
      medianLot: _round(median, 4),
      minimumLot: _round(lots.first, 4),
      maximumLot: _round(lots.last, 4),
      coefficientOfVariation: _round(coefficient),
      rangeRatio: _round(rangeRatio),
    );
  }

  TradingBehaviourProfile _behaviour(
    List<TradingJournalEntry> trades,
    TradingReportSummary summary,
    TradingPositionProfile position,
    double startingCapital,
  ) {
    final sorted = [...trades]
      ..sort((a, b) => a.openDate.compareTo(b.openDate));
    final layering = _layering(sorted);
    final revenge = _revenge(sorted);
    final gambling = _gambling(
      sorted,
      summary,
      position,
      layering,
      startingCapital,
    );
    final rawScore = math.max(
      revenge.component.score,
      gambling.component.score,
    );
    final recovery = _behaviourRecovery(sorted, rawScore);
    final finalScore = _round(rawScore * (1 - (recovery.percent / 100)));

    return TradingBehaviourProfile(
      revenge: revenge.component,
      gambling: gambling.component,
      layering: layering.component,
      rawScore: _round(rawScore),
      finalScore: finalScore,
      recoveryPercent: recovery.percent,
      recoveryBlocks: recovery.blocks,
      layeredTradeCount: layering.layeredTrades,
      layeredTradePercent: sorted.isEmpty
          ? 0
          : _round((layering.layeredTrades / sorted.length) * 100),
      maximumLayers: layering.maxLayers,
      overlapCount: layering.overlaps,
      adverseLayerCount: layering.adverse,
      overtradingDays: gambling.overtradeDays,
      maximumTradesPerDay: gambling.maxTradesPerDay,
      oversizedLossCount: gambling.oversizedLosses,
      checks: [...revenge.checks, ...layering.checks, ...gambling.checks],
    );
  }

  ({TradingBehaviourComponent component, List<TradingBehaviourCheck> checks})
  _revenge(List<TradingJournalEntry> trades) {
    final examples = <TradingBehaviourEvidence>[];
    var quick = 0;
    var lotIncreases = 0;
    var lossStreakReactions = 0;
    var delayTotal = 0;
    var lossStreak = 0;
    var highestTier = 0;

    for (var index = 0; index < trades.length - 1; index++) {
      final losingTrade = trades[index];
      lossStreak = losingTrade.profitLoss < 0 ? lossStreak + 1 : 0;
      if (losingTrade.profitLoss >= 0) continue;
      TradingJournalEntry? response;
      for (var nextIndex = index + 1; nextIndex < trades.length; nextIndex++) {
        final candidate = trades[nextIndex];
        if (!candidate.openDate.isBefore(losingTrade.closeDate)) {
          response = candidate;
          break;
        }
      }
      if (response == null) continue;
      final delay = response.openDate
          .difference(losingTrade.closeDate)
          .inMinutes;
      if (delay < 0 || delay > 180) continue;
      final ratio = losingTrade.lotSize > 0
          ? response.lotSize / losingTrade.lotSize
          : 0.0;
      final signals = <String>[];
      var eventScore = 0.0;
      if (delay <= 15) {
        signals.add('Immediate re-entry after loss');
        eventScore += 25;
        quick++;
      } else if (delay <= 60) {
        signals.add('Fast re-entry after loss');
        eventScore += 15;
        quick++;
      }
      if (ratio >= 2) {
        signals.add('Lot size doubled or more');
        eventScore += 25;
        lotIncreases++;
      } else if (ratio >= 1.5) {
        signals.add('Lot size increased after loss');
        eventScore += 15;
        lotIncreases++;
      }
      final samePair = losingTrade.pair == response.pair;
      final directionFlip = losingTrade.direction != response.direction;
      if (samePair) {
        signals.add('Same pair re-entry');
        eventScore += 8;
      }
      if (directionFlip) {
        signals.add('Direction flip after loss');
        eventScore += 8;
      }
      if (lossStreak >= 2) {
        signals.add('$lossStreak-loss streak reaction');
        eventScore += 14;
        lossStreakReactions++;
      }
      if (eventScore < 15) continue;
      final tierRank = (delay <= 15 && ratio >= 1.5) || ratio >= 2
          ? 3
          : eventScore >= 45 || ratio >= 1.5 || lossStreak >= 2
          ? 2
          : 1;
      highestTier = math.max(highestTier, tierRank);
      delayTotal += delay;
      examples.add(
        TradingBehaviourEvidence(
          tier: _tierLabel(tierRank),
          primaryTrade: losingTrade.id,
          secondaryTrade: response.id,
          pair: response.pair,
          delayLabel: _formatMinutes(delay),
          lotChange:
              '${losingTrade.lotSize.toStringAsFixed(2)} -> ${response.lotSize.toStringAsFixed(2)}',
          signals: signals.join(', '),
          side: response.directionLabel,
          lot: response.lotSize,
          profitLoss: response.profitLoss,
        ),
      );
    }

    final eventCount = examples.length;
    final score = math.min(
      100.0,
      (eventCount * 16) +
          (quick * 6) +
          (lotIncreases * 10) +
          (lossStreakReactions * 8),
    );
    final scoreTier = eventCount == 0
        ? 0
        : score >= 70
        ? 3
        : score >= 40
        ? 2
        : 1;
    final tier = math.max(highestTier, scoreTier);
    final averageDelay = eventCount == 0
        ? 0
        : (delayTotal / eventCount).round();
    final component = TradingBehaviourComponent(
      status: eventCount == 0 ? 'Clear' : '${_tierLabel(tier)} Revenge Tier',
      score: _round(score.toDouble()),
      tier: _tierLabel(tier),
      method: eventCount == 0
          ? 'No strong post-loss reaction pattern was detected.'
          : 'Post-loss timing, lot escalation, pair repetition, and loss streak pressure.',
      eventCount: eventCount,
      quickCount: quick,
      lotIncreaseCount: lotIncreases,
      averageDelayLabel: _formatMinutes(averageDelay),
      examples: examples.take(10).toList(growable: false),
    );
    return (
      component: component,
      checks: [
        TradingBehaviourCheck(
          name: 'Recent Loss Evidence',
          value: '$quick cooldown break(s)',
          status: quick > 0 ? 'Detected' : 'Clear',
          points: null,
          description:
              'Revenge trading requires post-loss evidence, not merely another order.',
        ),
        TradingBehaviourCheck(
          name: 'Risk or Lot Increase After Loss',
          value: '$lotIncreases event(s)',
          status: lotIncreases > 0 ? 'Detected' : 'Clear',
          points: null,
          description:
              'Flags when lot size increases materially after a closed loss.',
        ),
        TradingBehaviourCheck(
          name: 'Overall Revenge Score',
          value: '${score.toStringAsFixed(2)}/100',
          status: _tierLabel(tier),
          points: null,
          description:
              'Revenge risk is scored separately from gambling-style behaviour.',
        ),
      ],
    );
  }

  ({
    TradingBehaviourComponent component,
    List<TradingBehaviourCheck> checks,
    int layeredTrades,
    int maxLayers,
    int overlaps,
    int adverse,
  })
  _layering(List<TradingJournalEntry> trades) {
    final examples = <TradingBehaviourEvidence>[];
    final layeredIds = <String>{};
    var overlaps = 0;
    var fast = 0;
    var lotIncreases = 0;
    var adverse = 0;
    var maxLayers = 0;

    for (var i = 0; i < trades.length; i++) {
      final base = trades[i];
      for (var j = i + 1; j < trades.length; j++) {
        final layer = trades[j];
        if (base.pair != layer.pair || base.direction != layer.direction) {
          continue;
        }
        final delay = layer.openDate.difference(base.openDate).inMinutes;
        final overlap = layer.openDate.isBefore(base.closeDate);
        if (!overlap && delay > 180) continue;
        final active = trades.where((candidate) {
          return candidate.pair == layer.pair &&
              candidate.direction == layer.direction &&
              !candidate.openDate.isAfter(layer.openDate) &&
              candidate.closeDate.isAfter(layer.openDate);
        }).length;
        maxLayers = math.max(maxLayers, active);
        final ratio = base.lotSize > 0 ? layer.lotSize / base.lotSize : 0.0;
        final isAdverse =
            base.entryPrice > 0 &&
            layer.entryPrice > 0 &&
            (base.direction == 1
                ? layer.entryPrice < base.entryPrice
                : layer.entryPrice > base.entryPrice);
        final signals = <String>[];
        var eventScore = 0.0;
        if (overlap) {
          overlaps++;
          eventScore += 22;
          signals.add('Same-direction overlap');
        }
        if (delay <= 15) {
          fast++;
          eventScore += 16;
          signals.add('Immediate layering');
        } else if (delay <= 60) {
          fast++;
          eventScore += 10;
          signals.add('Fast layering');
        }
        if (ratio >= 2) {
          lotIncreases++;
          eventScore += 20;
          signals.add('Layer lot doubled or more');
        } else if (ratio >= 1.5) {
          lotIncreases++;
          eventScore += 12;
          signals.add('Layer lot increased');
        }
        if (isAdverse) {
          adverse++;
          eventScore += 18;
          signals.add('Layer added at an adverse price');
        } else if (base.entryPrice > 0 && layer.entryPrice > 0) {
          eventScore += 4;
          signals.add('Layer added at a favourable price');
        }
        if (active >= 3) {
          eventScore += 18;
          signals.add('$active active same-direction layers');
        } else if (active >= 2) {
          eventScore += 8;
          signals.add('Multiple same-direction layers');
        }
        if (eventScore < 22) continue;
        layeredIds.addAll([base.id, layer.id]);
        examples.add(
          TradingBehaviourEvidence(
            tier: eventScore >= 70
                ? 'High'
                : eventScore >= 40
                ? 'Mid'
                : 'Low',
            primaryTrade: base.id,
            secondaryTrade: layer.id,
            pair: layer.pair,
            delayLabel: _formatMinutes(math.max(0, delay)),
            lotChange:
                '${base.lotSize.toStringAsFixed(2)} -> ${layer.lotSize.toStringAsFixed(2)}',
            signals: signals.join(', '),
            side: layer.directionLabel,
            lot: layer.lotSize,
            profitLoss: layer.profitLoss,
          ),
        );
      }
    }

    final events = examples.length;
    final score = math.min(
      100.0,
      (events * 10) +
          (overlaps * 6) +
          (fast * 4) +
          (lotIncreases * 7) +
          (adverse * 8) +
          math.max(0, maxLayers - 2) * 8,
    );
    final status = events == 0
        ? 'Clear'
        : score >= 70
        ? 'High Layering Risk'
        : score >= 40
        ? 'Layering Watchlist'
        : 'Mild Layering Pattern';
    return (
      component: TradingBehaviourComponent(
        status: status,
        score: _round(score.toDouble()),
        tier: score >= 70
            ? 'High'
            : score >= 40
            ? 'Mid'
            : events > 0
            ? 'Low'
            : 'Clear',
        method:
            'Same-pair, same-direction entries are tested for timing, overlap, lot escalation, and adverse price.',
        eventCount: events,
        quickCount: fast,
        lotIncreaseCount: lotIncreases,
        averageDelayLabel: examples.isEmpty
            ? '0m'
            : _formatMinutes(
                examples
                        .map((item) => _minutes(item.delayLabel))
                        .fold<int>(0, (a, b) => a + b) ~/
                    examples.length,
              ),
        examples: examples.take(10).toList(growable: false),
      ),
      checks: [
        TradingBehaviourCheck(
          name: 'Same-direction Layering',
          value: '$events event(s)',
          status: events > 0 ? 'Detected' : 'Clear',
          points: null,
          description:
              'Looks for same-pair, same-direction entries close together or while exposure overlaps.',
        ),
        TradingBehaviourCheck(
          name: 'Overlapping Exposure',
          value: '$overlaps overlap(s)',
          status: overlaps > 0 ? 'Detected' : 'Clear',
          points: null,
          description:
              'Flags an additional trade opened before the earlier trade closed.',
        ),
        TradingBehaviourCheck(
          name: 'Adverse Price Layering',
          value: '$adverse layer(s)',
          status: adverse > 0 ? 'Detected' : 'Clear',
          points: null,
          description:
              'Checks whether new exposure was added as price moved against the initial entry.',
        ),
        TradingBehaviourCheck(
          name: 'Maximum Active Layers',
          value: '$maxLayers',
          status: maxLayers >= 3
              ? 'High'
              : maxLayers >= 2
              ? 'Watch'
              : 'Clear',
          points: null,
          description:
              'Maximum simultaneous same-pair, same-direction exposure.',
        ),
        TradingBehaviourCheck(
          name: 'Overall Layering Score',
          value: '${score.toStringAsFixed(2)}/100',
          status: status,
          points: null,
          description:
              'Layering is disclosed separately from revenge and gambling penalties.',
        ),
      ],
      layeredTrades: layeredIds.length,
      maxLayers: maxLayers,
      overlaps: overlaps,
      adverse: adverse,
    );
  }

  ({
    TradingBehaviourComponent component,
    List<TradingBehaviourCheck> checks,
    int overtradeDays,
    int maxTradesPerDay,
    int oversizedLosses,
  })
  _gambling(
    List<TradingJournalEntry> trades,
    TradingReportSummary summary,
    TradingPositionProfile position,
    ({
      TradingBehaviourComponent component,
      List<TradingBehaviourCheck> checks,
      int layeredTrades,
      int maxLayers,
      int overlaps,
      int adverse,
    })
    layering,
    double startingCapital,
  ) {
    final daily = <DateTime, int>{};
    for (final trade in trades) {
      final date = DateTime(
        trade.openDate.year,
        trade.openDate.month,
        trade.openDate.day,
      );
      daily[date] = (daily[date] ?? 0) + 1;
    }
    final maxTrades = daily.isEmpty ? 0 : daily.values.reduce(math.max);
    final overtradeDays = daily.values.where((count) => count >= 8).length;
    var rapidEntries = 0;
    for (var i = 1; i < trades.length; i++) {
      final minutes = trades[i].openDate
          .difference(trades[i - 1].openDate)
          .inMinutes;
      if (minutes >= 0 && minutes <= 10) rapidEntries++;
    }
    final capital = startingCapital > 0
        ? startingCapital
        : math.max(0, summary.currentBalance - summary.netProfitLoss);
    final oversized = capital <= 0
        ? <TradingJournalEntry>[]
        : trades
              .where(
                (trade) =>
                    trade.profitLoss < 0 &&
                    trade.profitLoss.abs() > capital * .03,
              )
              .toList();
    final overtradePoints = math.min(
      25.0,
      (overtradeDays * 8) + math.max(0, maxTrades - 8) * 2,
    );
    final lotVariationPoints = math.min(
      25.0,
      math.max(0, (position.coefficientOfVariation - 35) * .35) +
          math.max(0, (position.rangeRatio - 2.5) * 5),
    );
    final oversizedPoints = math.min(20.0, oversized.length * 7.0);
    final rapidPoints = math.min(15.0, rapidEntries * 3.0);
    final layeringPoints = math.min(20.0, layering.component.eventCount * 5.0);
    final concentrationPoints =
        summary.bestDayRule.totalGeneratedProfit > 0 &&
            summary.bestDayRule.scorePercent > 40
        ? math.min(10.0, ((summary.bestDayRule.scorePercent - 40) / 25) * 10)
        : 0.0;
    final score = _round(
      math.min(
        100,
        overtradePoints +
            lotVariationPoints +
            oversizedPoints +
            rapidPoints +
            layeringPoints +
            concentrationPoints,
      ),
    );
    final tierRank = trades.isEmpty
        ? 0
        : score >= 70
        ? 3
        : score >= 45
        ? 2
        : score >= 20
        ? 1
        : 0;
    final evidence = <TradingBehaviourEvidence>[
      for (final trade in oversized.take(10))
        TradingBehaviourEvidence(
          tier: 'High',
          primaryTrade: trade.id,
          secondaryTrade: '',
          pair: trade.pair,
          delayLabel: '-',
          lotChange: trade.lotSize.toStringAsFixed(2),
          signals: 'Loss exceeded 3% of available capital',
          side: trade.directionLabel,
          lot: trade.lotSize,
          profitLoss: trade.profitLoss,
        ),
    ];
    final status = tierRank == 0
        ? 'Clear'
        : '${_tierLabel(tierRank)} Gambling Tier';

    return (
      component: TradingBehaviourComponent(
        status: status,
        score: score,
        tier: _tierLabel(tierRank),
        method:
            'Trade pace, lot-size instability, oversized losses, rapid entries, layered exposure, and profit concentration.',
        eventCount: overtradeDays + oversized.length + rapidEntries,
        quickCount: rapidEntries,
        lotIncreaseCount: position.coefficientOfVariation > 35 ? 1 : 0,
        averageDelayLabel: '-',
        examples: evidence,
      ),
      checks: [
        TradingBehaviourCheck(
          name: 'Overtrading Bursts',
          value: '$overtradeDays day(s), max $maxTrades trades/day',
          status: overtradePoints >= 15
              ? 'High'
              : overtradePoints > 0
              ? 'Watch'
              : 'Clear',
          points: _round(overtradePoints.toDouble()),
          description: 'Flags days with eight or more trades.',
        ),
        TradingBehaviourCheck(
          name: 'Position Size Instability',
          value:
              '${position.coefficientOfVariation.toStringAsFixed(2)}% CV, ${position.rangeRatio.toStringAsFixed(2)}x range',
          status: lotVariationPoints >= 15
              ? 'High'
              : lotVariationPoints > 0
              ? 'Watch'
              : 'Clear',
          points: _round(lotVariationPoints.toDouble()),
          description:
              'Large lot-size swings can indicate bet-sizing rather than planned risk.',
        ),
        TradingBehaviourCheck(
          name: 'Oversized Losses',
          value: capital > 0
              ? '${oversized.length} loss(es) above 3% capital'
              : 'Capital unavailable',
          status: oversized.isNotEmpty
              ? 'Detected'
              : capital > 0
              ? 'Clear'
              : 'No Data',
          points: _round(oversizedPoints),
          description:
              'Uses 3% of capital as a proxy threshold when capital is available.',
        ),
        TradingBehaviourCheck(
          name: 'Rapid-fire Entries',
          value: '$rapidEntries entries within 10 minutes',
          status: rapidPoints >= 9
              ? 'High'
              : rapidPoints > 0
              ? 'Watch'
              : 'Clear',
          points: _round(rapidPoints),
          description:
              'Detects repeated fast entries when combined with other risk markers.',
        ),
        TradingBehaviourCheck(
          name: 'Overall Gambling Score',
          value: '${score.toStringAsFixed(2)}/100',
          status: status,
          points: null,
          description:
              'The final gambling tier combines all supported risk markers.',
        ),
      ],
      overtradeDays: overtradeDays,
      maxTradesPerDay: maxTrades,
      oversizedLosses: oversized.length,
    );
  }

  ({double percent, int blocks}) _behaviourRecovery(
    List<TradingJournalEntry> trades,
    double rawScore,
  ) {
    if (trades.length < 8 || rawScore <= 0) return (percent: 0, blocks: 0);
    var improvingBlocks = 0;
    for (var end = trades.length; end >= 8; end -= 8) {
      final block = trades.sublist(end - 8, end);
      final disciplined = block.where((trade) {
        return trade.followedTradingPlan &&
            (trade.disciplineRating == null || trade.disciplineRating! >= 6);
      }).length;
      if (disciplined >= 6) {
        improvingBlocks++;
      } else {
        break;
      }
    }
    return (
      percent: math.min(40.0, improvingBlocks * 20.0),
      blocks: improvingBlocks,
    );
  }

  TradingScoreProfile _score(
    TradingReportSummary summary,
    TradingPositionProfile position,
    TradingBehaviourProfile behaviour,
  ) {
    if (!summary.hasTrades) {
      return const TradingScoreProfile(
        score: 0,
        rawScore: 0,
        baseScore: 0,
        grade: 'N/A',
        meaning:
            'No score yet. Add closed trades to activate the scoring model.',
        behaviourPenaltyPercent: 0,
        behaviourPenaltyPoints: 0,
        isProvisional: false,
        confidencePercent: 0,
        components: [],
      );
    }

    final win = _winRateScore(summary.winRate);
    final risk = _riskRewardScore(summary);
    final recovery = _recoveryScore(summary);
    final consistency = _consistencyScore(summary.bestDayRule);
    final positionPoints = _round((position.score / 100) * 5);
    final expectancy = _expectancyScore(summary.expectancy);
    final drawdown = _drawdownPenalty(summary.overallLossPercent);
    final components = <TradingScoreComponent>[
      TradingScoreComponent(
        metric: 'Win Rate',
        value: '${summary.winRate.toStringAsFixed(2)}%',
        grade: win.grade,
        points: win.points,
        maximumPoints: 30,
        status: win.points >= 20 ? 'Strong' : 'Developing',
        formula: 'Winning trades divided by closed winning plus losing trades.',
        calculation:
            '${summary.winningTrades} wins / ${summary.winningTrades + summary.losingTrades} closed trades',
      ),
      TradingScoreComponent(
        metric: 'Risk Reward',
        value: summary.profitFactorLabel,
        grade: risk.grade,
        points: risk.points,
        maximumPoints: 30,
        status: risk.points >= 20 ? 'Strong' : 'Developing',
        formula: 'Total winning P/L divided by total losing P/L.',
        calculation:
            '${summary.totalProfit.toStringAsFixed(2)}u profit / ${summary.totalLoss.toStringAsFixed(2)}u loss',
      ),
      TradingScoreComponent(
        metric: 'Recovery Factor',
        value: summary.recoveryFactorLabel,
        grade: recovery.grade,
        points: recovery.points,
        maximumPoints: 15,
        status: recovery.points >= 12
            ? 'Strong'
            : recovery.points >= 7
            ? 'Healthy'
            : 'Developing',
        formula: 'Net P/L divided by maximum drawdown.',
        calculation:
            '${summary.netProfitLoss.toStringAsFixed(2)}u / ${summary.maxDrawdownAmount.toStringAsFixed(2)}u drawdown',
      ),
      TradingScoreComponent(
        metric: 'Best Day Consistency',
        value: '${summary.bestDayRule.scorePercent.toStringAsFixed(2)}%',
        grade: consistency.grade,
        points: consistency.points,
        maximumPoints: 10,
        status: summary.bestDayRule.status,
        formula: 'Biggest winning day divided by total generated profit.',
        calculation:
            '${summary.bestDayRule.bestWinningDay.toStringAsFixed(2)}u / ${summary.bestDayRule.totalGeneratedProfit.toStringAsFixed(2)}u',
      ),
      TradingScoreComponent(
        metric: 'Position Consistency',
        value: '${position.score.toStringAsFixed(2)}/100',
        grade: position.grade,
        points: positionPoints,
        maximumPoints: 5,
        status: position.status,
        formula: 'Position consistency score multiplied by 5%.',
        calculation:
            '${position.score.toStringAsFixed(2)}/100 x 5 pts = ${positionPoints.toStringAsFixed(2)} pts',
      ),
      TradingScoreComponent(
        metric: 'Expectancy',
        value: '${summary.expectancy.toStringAsFixed(2)}u',
        grade: expectancy.grade,
        points: expectancy.points,
        maximumPoints: 10,
        status: summary.expectancy > 0
            ? 'Positive'
            : summary.expectancy < 0
            ? 'Negative'
            : 'Flat',
        formula: '(Win rate x average win) - (loss rate x average loss).',
        calculation:
            '${summary.averageWin.toStringAsFixed(2)}u avg win / ${summary.averageLoss.toStringAsFixed(2)}u avg loss',
      ),
      TradingScoreComponent(
        metric: 'Drawdown Penalty',
        value: '${summary.overallLossPercent.toStringAsFixed(2)}%',
        grade: drawdown.grade,
        points: -drawdown.points,
        maximumPoints: 0,
        status: drawdown.points > 0 ? 'Penalty' : 'No Penalty',
        formula: 'Journal score loss pressure percentage.',
        calculation:
            '${summary.overallLossPercent.toStringAsFixed(2)}% current loss pressure',
        isPenalty: true,
      ),
    ];
    final base = _round(
      components.fold<double>(0, (sum, item) => sum + item.points),
    );
    final tierRank = math.max(
      _tierRank(behaviour.revenge.tier),
      _tierRank(behaviour.gambling.tier),
    );
    final penaltyPercent = tierRank >= 3
        ? 10.0
        : tierRank >= 2
        ? 5.0
        : 0.0;
    final penalty = _round(base * penaltyPercent / 100);
    components.add(
      TradingScoreComponent(
        metric: 'Behaviour Tier Penalty',
        value: '${penaltyPercent.toStringAsFixed(0)}%',
        grade: tierRank == 0 ? 'Clear' : _tierLabel(tierRank),
        points: -penalty,
        maximumPoints: 0,
        status: penalty > 0 ? 'Behaviour Penalty' : 'No Behaviour Penalty',
        formula: 'Mid tier subtracts 5%; high or critical tier subtracts 10%.',
        calculation:
            'Base ${base.toStringAsFixed(2)} pts x ${penaltyPercent.toStringAsFixed(0)}%',
        isPenalty: true,
      ),
    );
    final raw = _round((base - penalty).clamp(0, 100));
    final provisional = summary.totalTrades < 10;
    final confidence = math.min(100.0, summary.totalTrades / 10 * 100);
    var finalScore = raw;
    if (provisional) {
      final strongRisk =
          summary.maxDailyLossPercent >= 5 ||
          summary.maxDrawdownPercent > 10 ||
          behaviour.finalScore >= 65 ||
          (summary.netProfitLoss < 0 && summary.overallLossPercent > 2);
      if (!strongRisk) {
        final controlled =
            summary.maxDrawdownPercent <= 5 &&
            summary.overallLossPercent <= 2 &&
            summary.maxDailyLossPercent < 5 &&
            behaviour.finalScore < 45;
        final recovered =
            summary.totalLoss > 0 &&
            summary.totalProfit >= summary.totalLoss &&
            summary.netProfitLoss >= 0;
        final baseline = recovered && controlled
            ? 68.0
            : summary.netProfitLoss >= 0 && controlled
            ? 64.0
            : summary.netProfitLoss >= 0 && behaviour.finalScore < 45
            ? 58.0
            : 50.0;
        final weight = (summary.totalTrades / 10).clamp(.25, .85);
        final cap = summary.totalTrades <= 3 ? 72.0 : 80.0;
        final blended = (raw * weight) + (baseline * (1 - weight));
        finalScore = _round(math.min(cap, math.max(raw, blended)));
      }
    }
    final grade = _scoreGrade(finalScore, provisional: provisional);
    final meaning = provisional
        ? 'This score is provisional because fewer than 10 closed trades are available.'
        : finalScore >= 85
        ? 'Exceptional: elite trading performance across the core score metrics.'
        : finalScore >= 70
        ? 'Good: strong trading foundation with only minor improvement areas.'
        : finalScore >= 50
        ? 'Intermediate: adequate performance with clear development opportunities.'
        : finalScore >= 39
        ? 'Below average: significant areas need improvement.'
        : 'Poor: immediate review and stronger risk control are required.';

    return TradingScoreProfile(
      score: finalScore,
      rawScore: raw,
      baseScore: base,
      grade: grade,
      meaning: meaning,
      behaviourPenaltyPercent: penaltyPercent,
      behaviourPenaltyPoints: penalty,
      isProvisional: provisional,
      confidencePercent: _round(confidence),
      components: List.unmodifiable(components),
    );
  }

  TradingTraderLevelProfile _level(
    TradingReportSummary summary,
    TradingScoreProfile score,
    TradingPositionProfile position,
    TradingBehaviourProfile behaviour,
  ) {
    final sampleMode = summary.totalTrades == 0
        ? 'No Data'
        : summary.totalTrades < 10
        ? 'Small Sample'
        : 'Standard Sample';
    final baseLevel = summary.totalTrades == 0
        ? 1
        : (score.score.clamp(0, 100) ~/ 10 + 1).clamp(1, 10);
    var cap = 10;
    final reasons = <String>[];
    final revengeRank = _tierRank(behaviour.revenge.tier);
    final gamblingRank = _tierRank(behaviour.gambling.tier);
    if (summary.maxDailyLossPercent >= 5 || behaviour.finalScore >= 85) {
      cap = math.min(cap, 2);
      reasons.add('Critical behaviour or daily-loss evidence is active.');
    } else if (gamblingRank >= 3 || behaviour.gambling.score >= 65) {
      cap = math.min(cap, 3);
      reasons.add('High gambling-style behaviour is active.');
    } else if (revengeRank >= 3 || behaviour.revenge.score >= 65) {
      cap = math.min(cap, 4);
      reasons.add('High revenge-trading behaviour is active.');
    } else if (behaviour.finalScore >= 40 ||
        revengeRank >= 2 ||
        gamblingRank >= 2) {
      cap = math.min(cap, 6);
      reasons.add('Medium behaviour risk keeps the profile under monitoring.');
    }
    if (summary.totalTrades > 0 && summary.totalTrades < 10) {
      final sampleCap = summary.totalTrades <= 1
          ? 7
          : summary.totalTrades <= 2
          ? 8
          : summary.totalTrades <= 4
          ? 9
          : 10;
      if (sampleCap < cap) {
        reasons.add('Sample depth limits mature profile progression.');
      }
      cap = math.min(cap, sampleCap);
    }
    if (summary.netProfitLoss < 0) {
      cap = math.min(cap, 5);
      reasons.add(
        'Negative net P/L caps progression until recovery above break even.',
      );
    }
    if (summary.maxDrawdownPercent > 20) {
      cap = math.min(cap, 4);
      reasons.add('Drawdown above 20% activates a high-risk cap.');
    } else if (summary.maxDrawdownPercent > 10) {
      cap = math.min(cap, 6);
      reasons.add('Drawdown above 10% keeps the trader under monitoring.');
    }
    if (position.score > 0 && position.score < 45 && summary.totalTrades >= 5) {
      cap = math.min(cap, 6);
      reasons.add(
        'Low position-sizing consistency keeps the trader under monitoring.',
      );
    }
    if (!summary.bestDayRule.passed &&
        summary.netProfitLoss > 0 &&
        summary.totalTrades >= 10) {
      cap = math.min(cap, 8);
      reasons.add('Profit is too concentrated in the best winning day.');
    }
    final level = math.min(baseLevel, cap);
    final small = sampleMode == 'Small Sample';
    final label = _levelLabel(level, small: small);
    final next = level >= 10
        ? 'Highest Level'
        : _levelLabel(level + 1, small: small);
    return TradingTraderLevelProfile(
      levelNumber: level,
      label: label,
      sampleMode: sampleMode,
      classificationScore: score.score,
      baseLevel: baseLevel,
      capLevel: cap,
      capActive: level < baseLevel || cap < 10,
      capReason: reasons.join(' '),
      nextLevel: next,
      rewardEstimate: _round(math.max(0, summary.netProfitLoss) * .8),
      highestRewardEstimate: _round(math.max(0, summary.largestWin) * .8),
      profileNote: _levelDescription(level, small: small),
    );
  }

  TradingHedgingProfile _hedging(List<TradingJournalEntry> trades) {
    final affected = <String>{};
    var events = 0;
    for (var i = 0; i < trades.length; i++) {
      for (var j = i + 1; j < trades.length; j++) {
        final left = trades[i];
        final right = trades[j];
        if (left.pair != right.pair || left.direction == right.direction) {
          continue;
        }
        final overlaps =
            left.openDate.isBefore(right.closeDate) &&
            right.openDate.isBefore(left.closeDate);
        if (overlaps) {
          events++;
          affected.addAll([left.id, right.id]);
        }
      }
    }
    return TradingHedgingProfile(
      detected: events > 0,
      eventCount: events,
      affectedTrades: affected.length,
      description: events > 0
          ? 'Opposite-direction positions overlapped on the same instrument.'
          : 'No overlapping opposite-direction exposure was detected.',
    );
  }

  String _performanceStatus(
    TradingReportSummary summary,
    List<TradingRuleMonitorItem> rules,
  ) {
    if (!summary.hasTrades) return 'No Data';
    if (rules.any((rule) => rule.breached)) return 'Risk Breach';
    final passed = rules.where((rule) => rule.status == 'Passed').length;
    return passed >= 3 &&
            summary.bestDayRule.passed &&
            summary.grossProfitRule.passed
        ? 'Challenge Ready'
        : 'Monitoring';
  }

  String _traderStyle(
    TradingBehaviourProfile behaviour,
    TradingPositionProfile position,
  ) {
    if (behaviour.gambling.score >= 65) return 'Gambling-Risk Trader';
    if (behaviour.revenge.score >= 65) return 'Reactive Trader';
    if (behaviour.finalScore >= 40) return 'Behaviour Under Review';
    if (position.score < 45 && position.score > 0) {
      return 'Dynamic-Sizing Trader';
    }
    return 'Controlled Trader';
  }

  List<String> _strengths(
    TradingReportSummary summary,
    List<TradingBreakdownStat> pairs,
    List<TradingBreakdownStat> sessions,
    TradingPositionProfile position,
  ) {
    final values = <String>[];
    if (summary.netProfitLoss > 0) {
      values.add(
        'Positive net P/L of ${summary.netProfitLoss.toStringAsFixed(2)}u.',
      );
    }
    if (summary.winRate >= 50) {
      values.add('Healthy win rate at ${summary.winRate.toStringAsFixed(2)}%.');
    }
    if (summary.profitFactor != null && summary.profitFactor! >= 1) {
      values.add('Favourable profit factor of ${summary.profitFactorLabel}.');
    }
    if (summary.expectancy > 0) {
      values.add(
        'Positive expectancy of ${summary.expectancy.toStringAsFixed(2)}u per trade.',
      );
    }
    if (position.score >= 70) {
      values.add('Position sizing is comparatively consistent.');
    }
    final bestPair = [...pairs]
      ..sort((a, b) => b.profitLoss.compareTo(a.profitLoss));
    if (bestPair.isNotEmpty && bestPair.first.profitLoss > 0) {
      values.add('Strongest instrument is ${bestPair.first.label}.');
    }
    final bestSession = [...sessions]
      ..sort((a, b) => b.profitLoss.compareTo(a.profitLoss));
    if (bestSession.isNotEmpty && bestSession.first.trades > 0) {
      values.add('Best session is ${bestSession.first.label}.');
    }
    return values.isEmpty
        ? ['More closed trades are needed to confirm strengths.']
        : values;
  }

  List<String> _risks(
    TradingReportSummary summary,
    TradingBehaviourProfile behaviour,
    TradingPositionProfile position,
  ) {
    final values = <String>[];
    if (behaviour.layering.eventCount > 0) {
      values.add(
        'Layering or averaging behaviour appeared in the selected sample.',
      );
    }
    if (position.score < 55 && position.score > 0) {
      values.add(
        'Lot sizing is inconsistent and may indicate unstable risk planning.',
      );
    }
    if (!summary.bestDayRule.passed &&
        summary.bestDayRule.totalGeneratedProfit > 0) {
      values.add('Profit is too concentrated in the best day.');
    }
    if (behaviour.revenge.eventCount > 0) {
      values.add('Post-loss reaction evidence is present.');
    }
    if (behaviour.gambling.score >= 20) {
      values.add('Gambling-style risk markers require review.');
    }
    if (summary.maxDrawdownPercent > 10) {
      values.add('Maximum drawdown is above the 10% monitor level.');
    }
    return values.isEmpty
        ? ['No material behaviour or drawdown risk marker is active.']
        : values;
  }

  List<String> _coaching(
    TradingBehaviourProfile behaviour,
    TradingPositionProfile position,
    TradingReportSummary summary,
  ) {
    final values = <String>[];
    if (behaviour.layering.eventCount > 0) {
      values.add(
        'Define when layering is allowed, the maximum layers, and total exposure limit.',
      );
    }
    if (position.score < 70) {
      values.add('Standardise lot sizing around a planned risk model.');
    }
    if (!summary.bestDayRule.passed) {
      values.add(
        'Build balanced profit across multiple days instead of relying on one strong day.',
      );
    }
    if (behaviour.revenge.eventCount > 0) {
      values.add('Use a documented cooldown after every closed loss.');
    }
    return values.isEmpty
        ? ['Protect the current process and continue building sample depth.']
        : values;
  }

  String _resume(
    TradingReportSummary summary,
    TradingScoreProfile score,
    TradingTraderLevelProfile level,
    String style,
    String risk,
    TradingPositionProfile position,
  ) {
    return '${level.label}: the account score is ${score.score.toStringAsFixed(2)}/100 '
        '(${score.grade}), with ${summary.winRate.toStringAsFixed(2)}% win rate, '
        '${summary.profitFactorLabel} profit factor, ${summary.expectancy.toStringAsFixed(2)}u '
        'expectancy, and ${summary.maxDrawdownPercent.toStringAsFixed(2)}% maximum drawdown. '
        'The trading personality reads as $style. Risk is $risk and position consistency is '
        '${position.score.toStringAsFixed(2)}/100.';
  }

  ({double points, String grade}) _winRateScore(double value) => value >= 75
      ? (points: 30, grade: 'A')
      : value >= 65
      ? (points: 25, grade: 'B+')
      : value >= 60
      ? (points: 25, grade: 'B')
      : value >= 55
      ? (points: 20, grade: 'C+')
      : value >= 50
      ? (points: 17, grade: 'C')
      : value >= 45
      ? (points: 15, grade: 'D+')
      : value >= 35
      ? (points: 10, grade: 'D')
      : value > 0
      ? (points: 5, grade: 'E')
      : (points: 0, grade: 'N/A');

  ({double points, String grade}) _riskRewardScore(
    TradingReportSummary summary,
  ) {
    if (summary.totalProfit > 0 && summary.totalLoss == 0) {
      return (points: 30, grade: 'A+');
    }
    final value = summary.profitFactor;
    if (value == null) return (points: 0, grade: 'F');
    return value >= 5.75
        ? (points: 30, grade: 'A+')
        : value >= 3.45
        ? (points: 25, grade: 'A')
        : value >= 2.30
        ? (points: 20, grade: 'B')
        : value >= 1.73
        ? (points: 15, grade: 'C')
        : value >= 1.15
        ? (points: 10, grade: 'D')
        : value > .50
        ? (points: 5, grade: 'E')
        : (points: 0, grade: 'F');
  }

  ({double points, String grade}) _recoveryScore(TradingReportSummary summary) {
    if (summary.netProfitLoss <= 0) return (points: 0, grade: 'F');
    if (summary.maxDrawdownAmount <= 0) return (points: 15, grade: 'A+');
    final value =
        summary.recoveryFactor ??
        summary.netProfitLoss / summary.maxDrawdownAmount;
    return value >= 3
        ? (points: 15, grade: 'A+')
        : value >= 2
        ? (points: 12, grade: 'A')
        : value >= 1.5
        ? (points: 10, grade: 'B')
        : value >= 1
        ? (points: 7, grade: 'C')
        : value >= .5
        ? (points: 4, grade: 'D')
        : value > 0
        ? (points: 2, grade: 'E')
        : (points: 0, grade: 'F');
  }

  ({double points, String grade}) _consistencyScore(TradingBestDayRule rule) {
    if (rule.totalGeneratedProfit <= 0) return (points: 0, grade: 'N/A');
    return rule.scorePercent <= 20
        ? (points: 10, grade: 'S')
        : rule.scorePercent <= 30
        ? (points: 8, grade: 'A')
        : rule.scorePercent <= 40
        ? (points: 6, grade: 'B')
        : rule.scorePercent <= 50
        ? (points: 4, grade: 'C')
        : rule.scorePercent <= 65
        ? (points: 2, grade: 'D')
        : (points: 0, grade: 'F');
  }

  ({double points, String grade}) _expectancyScore(double value) => value >= 40
      ? (points: 10, grade: 'A+')
      : value >= 20
      ? (points: 7, grade: 'B')
      : value >= 10
      ? (points: 5, grade: 'C')
      : value >= 0
      ? (points: 0, grade: 'N/A')
      : value >= -10
      ? (points: -1, grade: 'F')
      : value >= -20
      ? (points: -2, grade: 'F')
      : (points: 0, grade: 'N/A');

  ({double points, String grade}) _drawdownPenalty(double value) => value > 90
      ? (points: 35, grade: 'F')
      : value > 80
      ? (points: 20, grade: 'F')
      : value > 60
      ? (points: 15, grade: 'F')
      : value > 40
      ? (points: 10, grade: 'F')
      : value > 30
      ? (points: 8, grade: 'E')
      : value > 20
      ? (points: 6, grade: 'D')
      : value > 10
      ? (points: 4, grade: 'C')
      : value > 5
      ? (points: 2, grade: 'B')
      : value > 2
      ? (points: 1, grade: 'A')
      : (points: 0, grade: 'A+');

  String _scoreGrade(double value, {required bool provisional}) {
    final grade = value >= 95
        ? 'S'
        : value >= 90
        ? 'A+'
        : value >= 85
        ? 'A'
        : value >= 80
        ? 'A-'
        : value >= 75
        ? 'B+'
        : value >= 70
        ? 'B'
        : value >= 65
        ? 'B-'
        : value >= 60
        ? 'C+'
        : value >= 55
        ? 'C'
        : value >= 50
        ? 'C-'
        : value >= 45
        ? 'D'
        : value >= 40
        ? 'D-'
        : value >= 30
        ? 'E'
        : 'F';
    return provisional ? 'Provisional $grade' : grade;
  }

  String _positionGrade(double value) => value >= 90
      ? 'A+'
      : value >= 80
      ? 'A'
      : value >= 70
      ? 'B'
      : value >= 60
      ? 'C'
      : value >= 45
      ? 'D'
      : 'F';

  int _tierRank(String value) => switch (value.toLowerCase()) {
    'critical' => 4,
    'high' => 3,
    'mid' || 'medium' => 2,
    'low' => 1,
    _ => 0,
  };

  String _tierLabel(int rank) => switch (rank) {
    >= 4 => 'Critical',
    3 => 'High',
    2 => 'Mid',
    1 => 'Low',
    _ => 'Clear',
  };

  String _levelLabel(int level, {required bool small}) {
    const standard = [
      'No Data / Failed Profile',
      'Novice Trader',
      'Gambling Trader',
      'High-Risk Reactive Trader',
      'Developing Trader',
      'Intermediate Trader',
      'Consistent Trader',
      'Skilled Trader',
      'Professional Trader',
      'Elite Professional Trader',
    ];
    const sample = [
      'No Small-Sample Profile',
      'Small Sample Novice Trader',
      'Small Sample Gambling Trader',
      'Small Sample Reactive Trader',
      'Small Sample Developing Trader',
      'Controlled Small Sample Trader',
      'Promising Small Sample Trader',
      'Skilled Small Sample Trader',
      'Provisional Professional Trader',
      'Small Sample Professional Candidate',
    ];
    return (small ? sample : standard)[level.clamp(1, 10) - 1];
  }

  String _levelDescription(int level, {required bool small}) {
    if (small) {
      return 'The profile remains provisional until at least 10 closed trades are available.';
    }
    return level >= 9
        ? 'The trader shows professional quality with strong performance and risk control.'
        : level >= 7
        ? 'The trader is becoming consistent and can continue normal evaluation.'
        : level >= 5
        ? 'The trader is functional but still needs clearer edge and cleaner execution.'
        : 'Risk behaviour and execution quality require focused review.';
  }

  String _formatMinutes(int minutes) {
    if (minutes < 60) return '${minutes}m';
    final hours = minutes ~/ 60;
    final remaining = minutes % 60;
    return remaining == 0 ? '${hours}h' : '${hours}h ${remaining}m';
  }

  int _minutes(String label) {
    final hour = RegExp(r'(\d+)h').firstMatch(label);
    final minute = RegExp(r'(\d+)m').firstMatch(label);
    return (int.tryParse(hour?.group(1) ?? '') ?? 0) * 60 +
        (int.tryParse(minute?.group(1) ?? '') ?? 0);
  }

  double _round(double value, [int decimals = 2]) {
    if (!value.isFinite) return 0;
    return double.parse(value.toStringAsFixed(decimals));
  }
}
