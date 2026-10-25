import '../models/personality_guidance.dart';
import '../models/trading_plan_recommendation.dart';

class PersonalityGuidanceService {
  const PersonalityGuidanceService({this.now});

  final DateTime Function()? now;

  bool supports(TradingPlanQuestionnaireResponse response) {
    return const {
      'personality',
      'loss_reaction',
      'biggest_weakness',
    }.every((id) => response.answerFor(id)?.trim().isNotEmpty == true);
  }

  PersonalityGuidanceResult? generate({
    required TradingPlanQuestionnaireResponse response,
    required String resultVersion,
  }) {
    if (!supports(response)) return null;
    final personality = response.answerFor('personality')!;
    final lossReaction = response.answerFor('loss_reaction')!;
    final weakness = response.answerFor('biggest_weakness')!;
    final issues = response.answersFor('psychology_issues').toSet()
      ..add(weakness);
    final riskAnswer = response.answerFor('risk_per_trade') ?? 'not_sure';
    final maxTrades = response.answerFor('max_trades_per_day') ?? 'not_sure';

    final strengths = <String>[
      if (const {'patient', 'careful'}.contains(personality))
        'Patience and selective execution',
      if (personality == 'analytical') 'Structured market analysis',
      if (personality == 'aggressive') 'Decisive execution',
      if (personality == 'fearful') 'Awareness of downside risk',
      if (lossReaction == 'accept_and_stop' || lossReaction == 'stop_day')
        'Healthy response after a loss',
      if (strengthsFallback(personality))
        'Awareness of personal trading behaviour',
    ];
    final risks = <String>[
      if (personality == 'impulsive') 'Impulsive entries',
      if (personality == 'aggressive') 'Excessive position size or frequency',
      if (personality == 'emotional') 'Emotion-led execution',
      if (personality == 'overconfident') 'Overconfidence after winning',
      if (personality == 'fearful') 'Hesitation and early exits',
      if (issues.contains('fomo')) 'FOMO',
      if (issues.contains('revenge_trading') || lossReaction == 'want_win_back')
        'Revenge trading',
      if (issues.contains('overtrading')) 'Overtrading',
      if (lossReaction == 'increase_lot') 'Increasing risk after a loss',
    ];
    final styles = switch (personality) {
      'patient' || 'careful' => const [
        'Swing trading',
        'Position trading',
        'Trend-following',
      ],
      'analytical' => const [
        'Rules-based trading',
        'Intraday trading',
        'Trend-following',
      ],
      'aggressive' => const [
        'Rules-based intraday trading',
        'Breakout trading with fixed risk',
      ],
      'fearful' => const [
        'Rules-based trading',
        'Swing trading with predefined risk',
      ],
      _ => const ['Rules-based trading', 'Swing trading'],
    };
    final strategies = <String>[
      if (personality == 'analytical') ...[
        'Market structure',
        'Rules-based confluence strategies',
        'EMA trend confirmation',
      ] else if (const {'patient', 'careful'}.contains(personality)) ...[
        'Support and resistance',
        'Breakout and retest',
        'Trend-following',
      ] else ...[
        'Market structure',
        'Support and resistance',
        'Rules-based confluence strategies',
      ],
    ];

    final items = <PersonalityGuidanceItem>[
      PersonalityGuidanceItem(
        id: 'risk-per-trade',
        category: PersonalityGuidanceCategory.risk,
        title: 'Use a fixed risk budget',
        explanation:
            'Your selected risk preference is ${riskAnswer == 'not_sure' ? 'not yet defined' : '$riskAnswer% per trade'}. A fixed limit reduces emotion-led position sizing.',
        recommendedAction:
            'Start with ${riskAnswer == 'not_sure' ? '0.5%' : '$riskAnswer%'} maximum risk per trade and review it only outside trading hours.',
        caution: 'This is educational guidance, not a required risk setting.',
        personalityFactor: 'Risk tolerance and rule adherence',
        relevance: riskAnswer == 'not_sure' ? .95 : .82,
      ),
      PersonalityGuidanceItem(
        id: 'trade-frequency',
        category: PersonalityGuidanceCategory.plan,
        title: 'Set a maximum trade frequency',
        explanation:
            'A written daily limit helps prevent overtrading and low-quality entries.',
        recommendedAction:
            'Use ${maxTrades == 'not_sure' ? '2 to 3' : maxTrades.replaceAll('_', ' ')} trades per day as a draft ceiling, with one setup checklist per entry.',
        caution:
            'A maximum is not a target; fewer qualified trades is acceptable.',
        personalityFactor: 'Patience and impulsiveness',
        relevance: issues.contains('overtrading') ? .98 : .74,
      ),
      PersonalityGuidanceItem(
        id: 'stop-loss',
        category: PersonalityGuidanceCategory.discipline,
        title: 'Require a stop loss before entry',
        explanation:
            'Predefining invalidation keeps risk decisions separate from live emotional pressure.',
        recommendedAction:
            'Do not activate a trade until the entry, stop loss, target, and planned R:R are recorded.',
        caution:
            'A stop loss reduces risk but cannot eliminate slippage or market gaps.',
        personalityFactor: 'Rule adherence and loss tolerance',
        relevance: issues.contains('moving_stop_loss') ? .99 : .84,
      ),
      if (issues.contains('fomo') || personality == 'impulsive')
        const PersonalityGuidanceItem(
          id: 'fomo-rule',
          category: PersonalityGuidanceCategory.psychology,
          title: 'Use a FOMO waiting rule',
          explanation:
              'Urgency after a moving price can lead to entries outside the planned location.',
          recommendedAction:
              'Wait for the next candle close and repeat the entry checklist; skip the trade if the planned entry is gone.',
          caution: 'Missing a trade is preferable to abandoning the risk plan.',
          personalityFactor: 'Impulsiveness and social influence',
          relevance: .98,
        ),
      if (issues.contains('revenge_trading') ||
          lossReaction == 'want_win_back' ||
          lossReaction == 'increase_lot')
        const PersonalityGuidanceItem(
          id: 'revenge-cooldown',
          category: PersonalityGuidanceCategory.psychology,
          title: 'Apply a cooldown after losses',
          explanation:
              'The urge to recover a loss immediately can increase frequency and position size.',
          recommendedAction:
              'Pause for at least 30 minutes after a loss and stop for the day after two consecutive losses.',
          caution: 'Do not increase size to recover a previous result.',
          personalityFactor: 'Stress response and loss reaction',
          relevance: .99,
        ),
      PersonalityGuidanceItem(
        id: 'entry-checklist',
        category: PersonalityGuidanceCategory.plan,
        title: 'Use a pre-trade permission checklist',
        explanation:
            'A short checklist turns analysis into repeatable execution criteria.',
        recommendedAction:
            'Confirm structure, location, trigger, stop loss, target, risk, session, and psychology readiness before entry.',
        caution: 'Do not treat checklist completion as a guarantee of profit.',
        personalityFactor: 'Need for structure and analytical preference',
        relevance: const {'analytical', 'careful'}.contains(personality)
            ? .94
            : .86,
      ),
      PersonalityGuidanceItem(
        id: 'journal-review',
        category: PersonalityGuidanceCategory.review,
        title: 'Schedule journal reviews',
        explanation:
            'Reviewing rule compliance separates process quality from one trade result.',
        recommendedAction:
            'Review screenshots and rule compliance after every trade, then review patterns weekly.',
        caution: 'Avoid changing a strategy based on a very small sample.',
        personalityFactor: 'Discipline and analytical preference',
        relevance: .9,
      ),
      PersonalityGuidanceItem(
        id: 'strategy-selection',
        category: PersonalityGuidanceCategory.strategy,
        title: 'Prefer explicit strategy rules',
        explanation:
            '${styles.first} may fit your current answers when entries and invalidation are objectively defined.',
        recommendedAction:
            'Review ${strategies.take(2).join(' or ')} and copy only the rules you understand into a draft strategy.',
        caution:
            'Personality fit does not guarantee that a strategy will be profitable.',
        personalityFactor: 'Decision style and need for structure',
        relevance: .78,
      ),
    ];

    return PersonalityGuidanceResult(
      personalityKey: personality,
      resultVersion: resultVersion.trim().isEmpty
          ? 'legacy-personality-result'
          : resultVersion,
      generatedAt: now?.call() ?? DateTime.now(),
      strengths: strengths.toSet().toList(growable: false),
      riskAreas: risks.toSet().toList(growable: false),
      suitableTradingStyles: styles,
      strategySuggestions: strategies.toSet().toList(growable: false),
      items: items,
    );
  }
}

bool strengthsFallback(String personality) => !const {
  'patient',
  'careful',
  'analytical',
  'aggressive',
  'fearful',
}.contains(personality);
