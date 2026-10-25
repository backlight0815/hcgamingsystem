import '../models/trading_plan_recommendation.dart';

class TradingPlanRecommendationService {
  const TradingPlanRecommendationService();

  static const questionnaireVersion = '2026.07.plan';

  static const sections = <TradingPlanQuestionSection>[
    TradingPlanQuestionSection(
      id: 'trader_profile',
      label: 'Trader Profile',
      questions: [
        TradingPlanQuestion(
          id: 'experience_length',
          label: 'How long have you been trading?',
          options: [
            TradingPlanAnswerOption(key: 'beginner', label: 'Beginner'),
            TradingPlanAnswerOption(
              key: 'less_6_months',
              label: 'Less than 6 months',
            ),
            TradingPlanAnswerOption(
              key: 'six_to_twelve_months',
              label: '6 months to 1 year',
            ),
            TradingPlanAnswerOption(key: 'one_to_three', label: '1 to 3 years'),
            TradingPlanAnswerOption(
              key: 'more_than_three',
              label: 'More than 3 years',
            ),
          ],
        ),
        TradingPlanQuestion(
          id: 'consistency',
          label: 'How consistent are you currently?',
          options: [
            TradingPlanAnswerOption(
              key: 'not_consistent',
              label: 'Not consistent',
            ),
            TradingPlanAnswerOption(
              key: 'sometimes',
              label: 'Sometimes consistent',
            ),
            TradingPlanAnswerOption(
              key: 'consistent_emotional',
              label: 'Consistent but still emotional',
            ),
            TradingPlanAnswerOption(
              key: 'consistent_disciplined',
              label: 'Consistent and disciplined',
            ),
          ],
        ),
        TradingPlanQuestion(
          id: 'account_type',
          label: 'What type of account do you trade?',
          options: [
            TradingPlanAnswerOption(
              key: 'personal_live',
              label: 'Personal live account',
            ),
            TradingPlanAnswerOption(
              key: 'prop_firm',
              label: 'Prop firm account',
            ),
            TradingPlanAnswerOption(key: 'demo', label: 'Demo account'),
            TradingPlanAnswerOption(
              key: 'live_and_prop',
              label: 'Both live and prop firm',
            ),
          ],
        ),
      ],
    ),
    TradingPlanQuestionSection(
      id: 'personality',
      label: 'Trading Personality',
      questions: [
        TradingPlanQuestion(
          id: 'personality',
          label: 'Which best describes your personality?',
          options: [
            TradingPlanAnswerOption(key: 'patient', label: 'Patient'),
            TradingPlanAnswerOption(key: 'aggressive', label: 'Aggressive'),
            TradingPlanAnswerOption(key: 'impulsive', label: 'Impulsive'),
            TradingPlanAnswerOption(key: 'careful', label: 'Careful'),
            TradingPlanAnswerOption(key: 'emotional', label: 'Emotional'),
            TradingPlanAnswerOption(key: 'analytical', label: 'Analytical'),
            TradingPlanAnswerOption(key: 'fearful', label: 'Fearful'),
            TradingPlanAnswerOption(
              key: 'overconfident',
              label: 'Confident but sometimes overconfident',
            ),
          ],
        ),
        TradingPlanQuestion(
          id: 'loss_reaction',
          label: 'How do you react after losing a trade?',
          options: [
            TradingPlanAnswerOption(
              key: 'accept_and_stop',
              label: 'Accept and stop',
            ),
            TradingPlanAnswerOption(
              key: 'want_win_back',
              label: 'Immediately want to win back',
            ),
            TradingPlanAnswerOption(
              key: 'emotional_control',
              label: 'Feel emotional but can control',
            ),
            TradingPlanAnswerOption(
              key: 'increase_lot',
              label: 'Increase lot size',
            ),
            TradingPlanAnswerOption(
              key: 'stop_day',
              label: 'Stop trading for the day',
            ),
            TradingPlanAnswerOption(
              key: 'keep_looking',
              label: 'Keep looking for new setups',
            ),
          ],
        ),
        TradingPlanQuestion(
          id: 'biggest_weakness',
          label: 'What is your biggest trading weakness?',
          options: [
            TradingPlanAnswerOption(key: 'fomo', label: 'FOMO'),
            TradingPlanAnswerOption(
              key: 'revenge_trading',
              label: 'Revenge trading',
            ),
            TradingPlanAnswerOption(key: 'overtrading', label: 'Overtrading'),
            TradingPlanAnswerOption(
              key: 'not_following_plan',
              label: 'Not following plan',
            ),
            TradingPlanAnswerOption(
              key: 'poor_risk',
              label: 'Poor risk management',
            ),
            TradingPlanAnswerOption(
              key: 'exit_too_early',
              label: 'Exit too early',
            ),
            TradingPlanAnswerOption(
              key: 'hold_losers',
              label: 'Hold losing trades too long',
            ),
            TradingPlanAnswerOption(key: 'no_checklist', label: 'No checklist'),
          ],
        ),
        TradingPlanQuestion(
          id: 'psychology_issues',
          label: 'Which psychology issues affect you?',
          allowsMultiple: true,
          required: false,
          options: [
            TradingPlanAnswerOption(key: 'fomo', label: 'FOMO'),
            TradingPlanAnswerOption(
              key: 'revenge_trading',
              label: 'Revenge trading',
            ),
            TradingPlanAnswerOption(key: 'overtrading', label: 'Overtrading'),
            TradingPlanAnswerOption(
              key: 'fear_entering_trade',
              label: 'Fear of entering trade',
            ),
            TradingPlanAnswerOption(
              key: 'fear_losing',
              label: 'Fear of losing',
            ),
            TradingPlanAnswerOption(
              key: 'closing_profit_too_early',
              label: 'Closing profit too early',
            ),
            TradingPlanAnswerOption(
              key: 'moving_stop_loss',
              label: 'Moving stop loss',
            ),
            TradingPlanAnswerOption(
              key: 'increasing_lot_after_loss',
              label: 'Increasing lot size after loss',
            ),
            TradingPlanAnswerOption(
              key: 'hesitation_after_losing_streak',
              label: 'Hesitation after losing streak',
            ),
            TradingPlanAnswerOption(
              key: 'cannot_follow_trading_plan',
              label: 'Cannot follow trading plan',
            ),
            TradingPlanAnswerOption(
              key: 'gambling_mindset',
              label: 'Gambling mindset',
            ),
            TradingPlanAnswerOption(
              key: 'all_in_behavior',
              label: 'All-in behavior',
            ),
            TradingPlanAnswerOption(
              key: 'lack_of_discipline',
              label: 'Lack of discipline',
            ),
            TradingPlanAnswerOption(
              key: 'lack_of_patience',
              label: 'Lack of patience',
            ),
            TradingPlanAnswerOption(
              key: 'signal_group_influence',
              label: 'Easily influenced by signal groups',
            ),
            TradingPlanAnswerOption(
              key: 'easily_frustrated',
              label: 'Easily frustrated',
            ),
            TradingPlanAnswerOption(
              key: 'emotional_after_missing_entry',
              label: 'Emotional trading after missing entry',
            ),
            TradingPlanAnswerOption(
              key: 'trading_when_tired',
              label: 'Trading when tired',
            ),
            TradingPlanAnswerOption(
              key: 'trading_during_stress',
              label: 'Trading during stress',
            ),
            TradingPlanAnswerOption(
              key: 'ignoring_daily_loss_limit',
              label: 'Ignoring daily loss limit',
            ),
            TradingPlanAnswerOption(
              key: 'ignoring_maximum_loss_limit',
              label: 'Ignoring maximum loss limit',
            ),
          ],
        ),
      ],
    ),
    TradingPlanQuestionSection(
      id: 'risk',
      label: 'Risk Management',
      questions: [
        TradingPlanQuestion(
          id: 'risk_per_trade',
          label: 'How much risk per trade should you use?',
          options: [
            TradingPlanAnswerOption(key: '0.25', label: '0.25%'),
            TradingPlanAnswerOption(key: '0.5', label: '0.5%'),
            TradingPlanAnswerOption(key: '1', label: '1%'),
            TradingPlanAnswerOption(key: '2', label: '2%'),
            TradingPlanAnswerOption(key: 'not_sure', label: 'Not sure'),
          ],
        ),
        TradingPlanQuestion(
          id: 'daily_loss',
          label: 'What is your maximum daily loss limit?',
          options: [
            TradingPlanAnswerOption(key: '1', label: '1%'),
            TradingPlanAnswerOption(key: '2', label: '2%'),
            TradingPlanAnswerOption(key: '3', label: '3%'),
            TradingPlanAnswerOption(key: '5', label: '5%'),
            TradingPlanAnswerOption(key: 'not_sure', label: 'Not sure'),
          ],
        ),
        TradingPlanQuestion(
          id: 'weekly_loss',
          label: 'What is your maximum weekly loss limit?',
          options: [
            TradingPlanAnswerOption(key: '3', label: '3%'),
            TradingPlanAnswerOption(key: '5', label: '5%'),
            TradingPlanAnswerOption(key: '8', label: '8%'),
            TradingPlanAnswerOption(key: '10', label: '10%'),
            TradingPlanAnswerOption(key: 'not_sure', label: 'Not sure'),
          ],
        ),
        TradingPlanQuestion(
          id: 'trades_per_day',
          label: 'How many trades should you allow per day?',
          options: [
            TradingPlanAnswerOption(key: '1', label: '1 trade'),
            TradingPlanAnswerOption(key: '2', label: '2 trades'),
            TradingPlanAnswerOption(key: '3', label: '3 trades'),
            TradingPlanAnswerOption(key: '5', label: '5 trades'),
            TradingPlanAnswerOption(
              key: 'no_limit',
              label: 'No limit currently',
            ),
          ],
        ),
        TradingPlanQuestion(
          id: 'minimum_rr',
          label: 'What minimum risk-reward ratio should you follow?',
          options: [
            TradingPlanAnswerOption(key: '1', label: '1:1'),
            TradingPlanAnswerOption(key: '1.5', label: '1:1.5'),
            TradingPlanAnswerOption(key: '2', label: '1:2'),
            TradingPlanAnswerOption(key: '3', label: '1:3'),
            TradingPlanAnswerOption(key: 'flexible', label: 'Flexible'),
          ],
        ),
      ],
    ),
    TradingPlanQuestionSection(
      id: 'routine',
      label: 'Trading Routine',
      questions: [
        TradingPlanQuestion(
          id: 'screen_time',
          label: 'How much time can you watch the market daily?',
          options: [
            TradingPlanAnswerOption(
              key: 'less_30',
              label: 'Less than 30 minutes',
            ),
            TradingPlanAnswerOption(
              key: 'thirty_to_one',
              label: '30 minutes to 1 hour',
            ),
            TradingPlanAnswerOption(key: 'one_to_three', label: '1 to 3 hours'),
            TradingPlanAnswerOption(
              key: 'more_than_three',
              label: 'More than 3 hours',
            ),
            TradingPlanAnswerOption(key: 'whole_day', label: 'Whole day'),
          ],
        ),
        TradingPlanQuestion(
          id: 'session',
          label: 'Which session do you normally trade?',
          options: [
            TradingPlanAnswerOption(key: 'asia', label: 'Asia'),
            TradingPlanAnswerOption(key: 'london', label: 'London'),
            TradingPlanAnswerOption(key: 'new_york', label: 'New York'),
            TradingPlanAnswerOption(
              key: 'london_new_york',
              label: 'London/New York overlap',
            ),
            TradingPlanAnswerOption(key: 'not_fixed', label: 'Not fixed'),
          ],
        ),
        TradingPlanQuestion(
          id: 'opportunity_preference',
          label:
              'Do you prefer fewer high-quality trades or more trading opportunities?',
          options: [
            TradingPlanAnswerOption(
              key: 'fewer_quality',
              label: 'Fewer high-quality trades',
            ),
            TradingPlanAnswerOption(
              key: 'more_opportunities',
              label: 'More opportunities',
            ),
            TradingPlanAnswerOption(key: 'balanced', label: 'Balanced'),
          ],
        ),
      ],
    ),
    TradingPlanQuestionSection(
      id: 'strategy_selection',
      label: 'Strategy Selection',
      questions: [
        TradingPlanQuestion(
          id: 'selected_strategy',
          label: 'Which trading strategy will this plan follow?',
          allowsMultiple: true,
          options: [
            TradingPlanAnswerOption(key: 'snr', label: 'SNR'),
            TradingPlanAnswerOption(key: 'smc', label: 'SMC'),
            TradingPlanAnswerOption(key: 'ict', label: 'ICT'),
            TradingPlanAnswerOption(
              key: 'market_structure',
              label: 'Market Structure',
            ),
            TradingPlanAnswerOption(key: 'macd', label: 'MACD'),
            TradingPlanAnswerOption(key: 'ema', label: 'EMA'),
            TradingPlanAnswerOption(key: 'trendline', label: 'Trendline'),
            TradingPlanAnswerOption(key: 'rsi', label: 'RSI'),
            TradingPlanAnswerOption(key: 'hybrid', label: 'Hybrid'),
            TradingPlanAnswerOption(key: 'not_sure_yet', label: 'Not sure yet'),
          ],
        ),
        TradingPlanQuestion(
          id: 'has_strategy',
          label: 'Do you already have your own strategy?',
          options: [
            TradingPlanAnswerOption(key: 'yes', label: 'Yes'),
            TradingPlanAnswerOption(key: 'no', label: 'No'),
            TradingPlanAnswerOption(
              key: 'still_developing',
              label: 'Still developing',
            ),
          ],
        ),
      ],
    ),
  ];

  TradingPlanRecommendationResult recommend(
    TradingPlanQuestionnaireResponse response,
  ) {
    final experience = _answer(response, 'experience_length');
    final consistency = _answer(response, 'consistency');
    final accountType = _answer(response, 'account_type');
    final personality = _answer(response, 'personality');
    final lossReaction = _answer(response, 'loss_reaction');
    final weakness = _answer(response, 'biggest_weakness');
    final screenTime = _answer(response, 'screen_time');
    final session = _answer(response, 'session');
    final opportunity = _answer(response, 'opportunity_preference');
    final selectedStrategy = _answer(response, 'selected_strategy');
    final hasStrategy = _answer(response, 'has_strategy');

    final selectedStrategyKeys = _selectedStrategyKeys(response);
    final selectedStrategyLabels = [
      for (final key in selectedStrategyKeys) _strategyLabel(key),
    ];
    final psychologyIssueKeys = _psychologyIssueKeys(
      response,
      weakness: weakness,
      lossReaction: lossReaction,
      personality: personality,
    );
    final psychologyIssueLabels = [
      for (final key in psychologyIssueKeys) _psychologyIssueLabel(key),
    ];
    final beginner =
        experience == 'beginner' ||
        experience == 'less_6_months' ||
        consistency == 'not_consistent' ||
        consistency == 'sometimes';
    final fomo =
        psychologyIssueKeys.contains('fomo') ||
        psychologyIssueKeys.contains('emotional_after_missing_entry') ||
        personality == 'impulsive';
    final revenge =
        psychologyIssueKeys.contains('revenge_trading') ||
        psychologyIssueKeys.contains('increasing_lot_after_loss') ||
        psychologyIssueKeys.contains('gambling_mindset') ||
        psychologyIssueKeys.contains('all_in_behavior') ||
        lossReaction == 'want_win_back' ||
        lossReaction == 'increase_lot';
    final overtrading =
        psychologyIssueKeys.contains('overtrading') ||
        psychologyIssueKeys.contains('trading_when_tired') ||
        psychologyIssueKeys.contains('trading_during_stress') ||
        psychologyIssueKeys.contains('ignoring_daily_loss_limit') ||
        lossReaction == 'keep_looking' ||
        opportunity == 'more_opportunities';
    final fearBased =
        psychologyIssueKeys.contains('fear_entering_trade') ||
        psychologyIssueKeys.contains('fear_losing') ||
        psychologyIssueKeys.contains('hesitation_after_losing_streak') ||
        personality == 'fearful';
    final disciplineRisk =
        psychologyIssueKeys.contains('cannot_follow_trading_plan') ||
        psychologyIssueKeys.contains('lack_of_discipline') ||
        psychologyIssueKeys.contains('lack_of_patience') ||
        psychologyIssueKeys.contains('signal_group_influence') ||
        psychologyIssueKeys.contains('easily_frustrated');
    final severeRisk =
        psychologyIssueKeys.contains('all_in_behavior') ||
        psychologyIssueKeys.contains('gambling_mindset') ||
        psychologyIssueKeys.contains('ignoring_maximum_loss_limit');
    final manyPsychologyIssues = psychologyIssueKeys.length >= 3;
    final propFirm =
        accountType == 'prop_firm' || accountType == 'live_and_prop';
    final noStrategy =
        selectedStrategyKeys.isEmpty ||
        selectedStrategy == 'not_sure_yet' ||
        selectedStrategyKeys.contains('not_sure_yet') ||
        hasStrategy == 'no' ||
        hasStrategy == 'still_developing';

    final riskPercent = _riskPercent(
      response,
      beginner: beginner,
      propFirm: propFirm,
      manyPsychologyIssues: manyPsychologyIssues || revenge,
      severeRisk: severeRisk,
    );
    final dailyLoss = _dailyLoss(
      response,
      beginner: beginner,
      propFirm: propFirm,
      revenge: revenge,
      overtrading: overtrading,
      manyPsychologyIssues: manyPsychologyIssues,
      severeRisk: severeRisk,
    );
    final weeklyLoss = _weeklyLoss(
      response,
      dailyLoss: dailyLoss,
      propFirm: propFirm,
    );
    final maxTrades = _maxTrades(
      response,
      beginner: beginner,
      fomo: fomo,
      revenge: revenge,
      overtrading: overtrading,
      manyPsychologyIssues: manyPsychologyIssues,
      severeRisk: severeRisk,
    );
    final rr = _minimumRiskReward(response, beginner: beginner);
    final strategyLabel = selectedStrategyLabels.isEmpty
        ? _strategyLabel(selectedStrategy)
        : selectedStrategyLabels.join(', ');
    final sessions = _sessions(session);

    return TradingPlanRecommendationResult(
      questionnaireVersion: questionnaireVersion,
      planName: _planName(
        beginner: beginner,
        propFirm: propFirm,
        fomo: fomo,
        revenge: revenge,
        overtrading: overtrading,
        manyPsychologyIssues: manyPsychologyIssues,
      ),
      traderProfileSummary: _lines([
        if (beginner)
          'You are building consistency, so the plan prioritizes simple rules and capital protection.'
        else
          'You have enough experience to follow a measurable personal trading rulebook.',
        if (fomo || revenge || overtrading)
          'Your main risk is behavioral, so the plan adds strict permission and stop rules.',
        if (manyPsychologyIssues)
          'Multiple psychology issues are selected, so this plan uses tighter risk, fewer trades, and stronger stop conditions.',
        if (fearBased)
          'Fear-based execution risk is present, so entries must be based on checklist completion rather than emotion.',
        if (propFirm)
          'This plan protects account survival and drawdown limits before growth.',
        if (noStrategy)
          'No trade is allowed until a clear strategy is selected or developed.',
      ]),
      selectedTradingStrategy: strategyLabel,
      accountType: _accountTypeLabel(accountType),
      tradingObjective: _lines([
        if (beginner) 'Build consistency first, not fast profit.',
        if (propFirm) 'Protect account status and avoid rule breaches.',
        if (!beginner && !propFirm)
          'Execute only planned trades with repeatable risk.',
      ]),
      riskManagementRules: _lines([
        'Risk per trade is fixed before entry.',
        'Do not increase lot size to recover losses.',
        if (beginner) 'Keep risk conservative until consistency is proven.',
        if (manyPsychologyIssues)
          'Risk stays reduced until at least 20 rule-following trades are reviewed.',
        if (severeRisk)
          'All-in behavior, gambling entries, and maximum-loss breaches end the trading session immediately.',
        if (propFirm) 'Protect daily drawdown and maximum loss limits first.',
        if (overtrading)
          'The max trades per day is a hard limit, not a target.',
      ]),
      riskPercent: riskPercent,
      maxDailyLossPercent: dailyLoss,
      maxWeeklyLossPercent: weeklyLoss,
      maxTradesPerDay: maxTrades,
      maxLossesPerDay: severeRisk ? 1 : 2,
      maxConsecutiveLosses: severeRisk || revenge ? 2 : 3,
      maxOpenTrades: 1,
      minimumRiskRewardRatio: rr,
      cooldownAfterLossMinutes: severeRisk
          ? 45
          : revenge
          ? 30
          : (fomo || overtrading || manyPsychologyIssues)
          ? 30
          : 15,
      tradingSessionRule: _sessionRule(session),
      tradingRoutine: _routine(screenTime, sessions),
      preTradeChecklist: _lines([
        'Selected strategy is confirmed.',
        'Risk size is calculated.',
        'Daily loss and trade count limits are checked.',
        'Mental state is calm enough to follow rules.',
        if (psychologyIssueLabels.isNotEmpty)
          'Current psychology issue checked: ${psychologyIssueLabels.join(', ')}.',
        if (noStrategy) 'No trade unless the strategy is clear.',
        if (fomo) 'Confirmed this is not a chase entry.',
      ]),
      entryPermissionRules: _lines([
        'A trade is allowed only when the selected strategy is present.',
        if (selectedStrategyLabels.length > 1)
          'Allowed strategies: ${selectedStrategyLabels.join(', ')}. Choose only one active strategy per trade.',
        'A trade is allowed only when risk, session, and checklist rules are satisfied.',
        if (noStrategy)
          'No discretionary entry while strategy is still developing.',
        if (beginner) 'Skip unclear structure and partial setups.',
      ]),
      noTradeConditions: _lines([
        'No trade when risk cannot be calculated.',
        'No trade after daily loss limit is reached.',
        'No trade when emotional state is unstable.',
        if (noStrategy) 'No trade without selected strategy.',
        if (fomo) 'No trade after chasing or missing a candle.',
        if (revenge) 'No trade immediately after a loss without cooldown.',
        if (overtrading) 'No trade after the max trade count is reached.',
        if (fearBased)
          'No trade when fear is causing hesitation after all checklist conditions are complete.',
        if (disciplineRisk)
          'No trade when signal groups, frustration, tiredness, or stress are driving the decision.',
        if (severeRisk)
          'No trade after any all-in urge, gambling urge, or maximum-loss-limit breach.',
        if (propFirm)
          'No high-risk news trade unless the strategy explicitly allows it.',
      ]),
      psychologyRules: _lines([
        'Follow the written plan even when a trade is missed.',
        if (beginner) 'Focus on consistency first, not profit.',
        if (fomo) 'Missing a trade is better than entering late.',
        if (revenge) 'Do not trade to recover losses.',
        ..._psychologyIssueRules(psychologyIssueKeys),
        if (personality == 'fearful')
          'Use smaller risk and wait for clear setups.',
      ]),
      personalityWarnings: _personalityWarnings(personality),
      lossRecoveryRules: _lines([
        'After a loss, pause before looking for another setup.',
        if (revenge) 'Stop after 2 consecutive losses.',
        if (revenge) 'Take a 15-30 minute cooldown after loss.',
        if (severeRisk)
          'Stop immediately when all-in or gambling urges appear.',
        'Never increase lot size after a loss.',
      ]),
      fomoPreventionRules: fomo
          ? _lines([
              'Do not chase candles.',
              'Wait for retest or the next valid setup.',
              'Take a cooldown after missing a trade.',
            ])
          : 'Do not chase missed moves. Wait for the next planned setup.',
      revengeTradingPreventionRules: revenge
          ? _lines([
              'Stop after 2 consecutive losses.',
              'No lot increase after loss.',
              'Do not trade to recover losses.',
            ])
          : 'After a loss, take the next trade only if it fully qualifies.',
      disciplineRules: _lines([
        'Breaking one rule ends the session.',
        'Checklist must be completed before every trade.',
        if (disciplineRisk)
          'If discipline is weak, reduce the session to one planned trade decision.',
        if (propFirm) 'No all-in and no overleveraging.',
        if (overtrading) 'Stop when max trades per day is reached.',
        if (manyPsychologyIssues)
          'Do not trade when more than one selected psychology issue is active at the same time.',
      ]),
      postTradeReviewRules: _lines([
        'Record screenshot, result, emotion, and rule compliance.',
        'Mark whether the selected strategy was followed.',
        'Write one improvement note before the next trade.',
      ]),
      weeklyReviewRules:
          'Review risk control, missed rules, best setups, and one improvement focus each week.',
      improvementNotes: _lines([
        if (beginner) 'Build a 20-trade sample before increasing risk.',
        if (fomo)
          'Track every missed trade and whether chasing would break the plan.',
        if (revenge) 'Review losing trades only after cooldown.',
        if (psychologyIssueLabels.isNotEmpty)
          'Review these selected issues weekly: ${psychologyIssueLabels.join(', ')}.',
        'Use the journal to identify the most repeated mistake.',
      ]),
      accountRules: _lines([
        'Stop trading when daily risk limit or max losing trades is reached.',
        if (psychologyIssueKeys.contains('ignoring_daily_loss_limit'))
          'Daily loss limit is a platform-level stop; no manual override.',
        if (psychologyIssueKeys.contains('ignoring_maximum_loss_limit'))
          'Maximum loss limit is an account survival rule; trading stops before breach.',
        if (propFirm)
          'Track daily loss, maximum loss, minimum trading days, and consistency requirements.',
      ]),
      newsFilterRule:
          'Avoid new entries shortly before high-impact news unless the selected strategy explicitly allows it.',
      propFirmRules: propFirm
          ? _lines([
              'Protect account first.',
              'No all-in and no overleveraging.',
              'Avoid revenge trading.',
              'Avoid high-risk news if not part of the strategy.',
              if (severeRisk)
                'Any all-in or gambling urge means the session is closed.',
            ])
          : '',
      afterWinRules:
          'After a win, keep the same risk and wait for the next valid setup.',
      afterLossRules:
          'After a loss, review whether rules were followed before trading again.',
      afterBreakingRules:
          'If a rule is broken, stop trading for the session and record the lesson.',
      dailyRoutine: _routine(screenTime, sessions),
      weeklyReviewRoutine:
          'Review statistics, repeated mistakes, best setups, and one improvement focus.',
      allowedSessions: sessions,
      allowedPairs: const [],
      selectedTradingStrategies: selectedStrategyLabels,
      psychologyIssues: psychologyIssueLabels,
    );
  }

  static String optionLabel(String questionId, String answerKey) {
    for (final section in sections) {
      for (final question in section.questions) {
        if (question.id != questionId) continue;
        return question.optionFor(answerKey)?.label ?? answerKey;
      }
    }
    return answerKey;
  }

  String _answer(TradingPlanQuestionnaireResponse response, String questionId) {
    return response.answerFor(questionId) ?? '';
  }

  List<String> _selectedStrategyKeys(
    TradingPlanQuestionnaireResponse response,
  ) {
    return _unique(
      response.answersFor('selected_strategy'),
    ).where((key) => key != 'not_sure_yet').toList(growable: false);
  }

  List<String> _psychologyIssueKeys(
    TradingPlanQuestionnaireResponse response, {
    required String weakness,
    required String lossReaction,
    required String personality,
  }) {
    return _unique([
      ...response.answersFor('psychology_issues'),
      if (weakness.isNotEmpty) weakness,
      if (lossReaction == 'want_win_back') 'revenge_trading',
      if (lossReaction == 'increase_lot') 'increasing_lot_after_loss',
      if (lossReaction == 'keep_looking') 'overtrading',
      if (personality == 'impulsive') 'fomo',
      if (personality == 'fearful') 'fear_entering_trade',
      if (personality == 'emotional') 'emotional_after_missing_entry',
    ]).where((key) => key != 'no_checklist').toList(growable: false);
  }

  List<String> _unique(Iterable<String> values) {
    final seen = <String>{};
    final result = <String>[];
    for (final value in values) {
      final key = value.trim();
      if (key.isEmpty || seen.contains(key)) continue;
      seen.add(key);
      result.add(key);
    }
    return result;
  }

  String _planName({
    required bool beginner,
    required bool propFirm,
    required bool fomo,
    required bool revenge,
    required bool overtrading,
    required bool manyPsychologyIssues,
  }) {
    if (propFirm) return 'Guided prop firm protection plan';
    if (revenge) return 'Guided revenge-control trading plan';
    if (fomo) return 'Guided FOMO-control trading plan';
    if (overtrading) return 'Guided overtrading-control trading plan';
    if (manyPsychologyIssues) {
      return 'Guided psychology protection trading plan';
    }
    if (beginner) return 'Guided beginner consistency plan';
    return 'Guided disciplined trading plan';
  }

  double _riskPercent(
    TradingPlanQuestionnaireResponse response, {
    required bool beginner,
    required bool propFirm,
    required bool manyPsychologyIssues,
    required bool severeRisk,
  }) {
    final selected = double.tryParse(_answer(response, 'risk_per_trade'));
    var risk = selected ?? (beginner || propFirm ? .5 : 1);
    if (beginner || propFirm) risk = risk.clamp(.25, .5);
    if (manyPsychologyIssues || severeRisk) risk = risk.clamp(.25, .5);
    if (severeRisk) risk = risk.clamp(.25, .25);
    return risk.toDouble();
  }

  double _dailyLoss(
    TradingPlanQuestionnaireResponse response, {
    required bool beginner,
    required bool propFirm,
    required bool revenge,
    required bool overtrading,
    required bool manyPsychologyIssues,
    required bool severeRisk,
  }) {
    final selected = double.tryParse(_answer(response, 'daily_loss'));
    var dailyLoss = selected ?? (beginner || propFirm ? 1 : 3);
    if (beginner || revenge || overtrading) dailyLoss = dailyLoss.clamp(1, 2);
    if (manyPsychologyIssues || severeRisk) dailyLoss = dailyLoss.clamp(1, 2);
    if (propFirm) dailyLoss = dailyLoss.clamp(1, 3);
    return dailyLoss.toDouble();
  }

  double _weeklyLoss(
    TradingPlanQuestionnaireResponse response, {
    required double dailyLoss,
    required bool propFirm,
  }) {
    final selected = double.tryParse(_answer(response, 'weekly_loss'));
    if (selected != null) return selected;
    return propFirm ? dailyLoss * 2 : dailyLoss * 3;
  }

  int _maxTrades(
    TradingPlanQuestionnaireResponse response, {
    required bool beginner,
    required bool fomo,
    required bool revenge,
    required bool overtrading,
    required bool manyPsychologyIssues,
    required bool severeRisk,
  }) {
    final selected = int.tryParse(_answer(response, 'trades_per_day'));
    var trades = selected ?? 3;
    if (beginner ||
        fomo ||
        revenge ||
        overtrading ||
        manyPsychologyIssues ||
        severeRisk) {
      trades = trades.clamp(1, 2).toInt();
    }
    if (severeRisk) trades = trades.clamp(1, 1).toInt();
    return trades;
  }

  double _minimumRiskReward(
    TradingPlanQuestionnaireResponse response, {
    required bool beginner,
  }) {
    final answer = _answer(response, 'minimum_rr');
    final selected = answer == 'flexible' ? null : double.tryParse(answer);
    if (selected != null) return selected;
    return beginner ? 2 : 1.5;
  }

  String _strategyLabel(String strategy) {
    return switch (strategy) {
      'snr' => 'SNR',
      'smc' => 'SMC',
      'ict' => 'ICT',
      'market_structure' => 'Market Structure',
      'macd' => 'MACD',
      'ema' => 'EMA',
      'trendline' => 'Trendline',
      'rsi' => 'RSI',
      'hybrid' => 'Hybrid',
      _ => 'Not sure yet',
    };
  }

  String _psychologyIssueLabel(String issue) {
    return switch (issue) {
      'fomo' => 'FOMO',
      'revenge_trading' => 'Revenge trading',
      'overtrading' => 'Overtrading',
      'fear_entering_trade' => 'Fear of entering trade',
      'fear_losing' => 'Fear of losing',
      'closing_profit_too_early' => 'Closing profit too early',
      'exit_too_early' => 'Closing profit too early',
      'moving_stop_loss' => 'Moving stop loss',
      'increasing_lot_after_loss' => 'Increasing lot size after loss',
      'hesitation_after_losing_streak' => 'Hesitation after losing streak',
      'cannot_follow_trading_plan' => 'Cannot follow trading plan',
      'not_following_plan' => 'Cannot follow trading plan',
      'poor_risk' => 'Poor risk management',
      'gambling_mindset' => 'Gambling mindset',
      'all_in_behavior' => 'All-in behavior',
      'lack_of_discipline' => 'Lack of discipline',
      'lack_of_patience' => 'Lack of patience',
      'signal_group_influence' => 'Easily influenced by signal groups',
      'easily_frustrated' => 'Easily frustrated',
      'emotional_after_missing_entry' =>
        'Emotional trading after missing entry',
      'trading_when_tired' => 'Trading when tired',
      'trading_during_stress' => 'Trading during stress',
      'ignoring_daily_loss_limit' => 'Ignoring daily loss limit',
      'ignoring_maximum_loss_limit' => 'Ignoring maximum loss limit',
      'hold_losers' => 'Hold losing trades too long',
      _ => issue,
    };
  }

  Iterable<String> _psychologyIssueRules(List<String> issues) {
    return [
      if (issues.contains('fomo') ||
          issues.contains('emotional_after_missing_entry'))
        'FOMO rule: a missed entry is not a valid entry; wait for retest or the next setup.',
      if (issues.contains('revenge_trading'))
        'Revenge rule: after a loss, the cooldown starts before any new chart analysis.',
      if (issues.contains('overtrading'))
        'Overtrading rule: max trades per day is a hard session lock, not a target.',
      if (issues.contains('fear_entering_trade') ||
          issues.contains('fear_losing') ||
          issues.contains('hesitation_after_losing_streak'))
        'Fear rule: if the checklist is complete and risk is reduced, follow the plan instead of hesitating.',
      if (issues.contains('closing_profit_too_early') ||
          issues.contains('exit_too_early'))
        'Early-exit rule: do not close profit early unless the written exit condition appears.',
      if (issues.contains('moving_stop_loss'))
        'Stop-loss rule: never move the stop farther from entry after the trade is live.',
      if (issues.contains('increasing_lot_after_loss'))
        'Lot-size rule: loss recovery by increasing lot size is forbidden.',
      if (issues.contains('gambling_mindset') ||
          issues.contains('all_in_behavior'))
        'Capital protection rule: all-in behavior, oversized risk, and gambling entries close the session.',
      if (issues.contains('cannot_follow_trading_plan') ||
          issues.contains('not_following_plan') ||
          issues.contains('lack_of_discipline'))
        'Plan-following rule: breaking one written rule ends the session.',
      if (issues.contains('lack_of_patience'))
        'Patience rule: no entry before the selected setup reaches its planned location.',
      if (issues.contains('signal_group_influence'))
        'Signal-group rule: outside signals cannot override the selected strategy or risk rules.',
      if (issues.contains('easily_frustrated') ||
          issues.contains('trading_during_stress') ||
          issues.contains('trading_when_tired'))
        'State rule: tired, stressed, or frustrated trading is blocked until the next session.',
      if (issues.contains('ignoring_daily_loss_limit'))
        'Daily-loss rule: once the daily loss limit is reached, trading stops immediately.',
      if (issues.contains('ignoring_maximum_loss_limit'))
        'Maximum-loss rule: maximum loss protection overrides all trading opportunities.',
      if (issues.contains('hold_losers'))
        'Loser-holding rule: exit at the planned invalidation; no waiting for hope.',
      if (issues.contains('poor_risk'))
        'Risk rule: every trade needs fixed risk, stop loss, and valid reward before entry.',
    ];
  }

  String _accountTypeLabel(String accountType) {
    return switch (accountType) {
      'prop_firm' => 'Prop firm account',
      'demo' => 'Demo account',
      'live_and_prop' => 'Both live and prop firm',
      _ => 'Personal live account',
    };
  }

  List<String> _sessions(String session) {
    return switch (session) {
      'asia' => ['Asian'],
      'london' => ['London'],
      'new_york' => ['New York'],
      'london_new_york' => ['London', 'New York'],
      _ => ['London', 'New York'],
    };
  }

  String _sessionRule(String session) {
    return switch (session) {
      'asia' => 'Trade only the Asia session unless the plan is updated.',
      'london' => 'Trade only the London session.',
      'new_york' => 'Trade only the New York session.',
      'london_new_york' =>
        'Trade only London/New York overlap or the selected London/New York sessions.',
      _ => 'Trade only when the session is planned before market entry.',
    };
  }

  String _routine(String screenTime, List<String> sessions) {
    final watchRule = switch (screenTime) {
      'less_30' => 'Prepare levels first and take only one planned decision.',
      'thirty_to_one' => 'Use a short watchlist and avoid constant monitoring.',
      'whole_day' => 'Use scheduled breaks to prevent overtrading.',
      _ =>
        'Review market context, risk limits, and checklist before the session.',
    };
    return _lines([
      'Trade sessions: ${sessions.join(', ')}.',
      watchRule,
      'Before trading, review calendar, risk limits, and selected strategy.',
    ]);
  }

  String _personalityWarnings(String personality) {
    return _lines([
      if (personality == 'impulsive')
        'Impulsive traders must slow down and complete the checklist.',
      if (personality == 'aggressive')
        'Aggressive traders must treat risk limits as non-negotiable.',
      if (personality == 'emotional')
        'Emotional traders must pause when frustration, fear, or excitement is high.',
      if (personality == 'overconfident')
        'Overconfidence after wins can create oversized risk and unnecessary trades.',
      if (personality == 'fearful')
        'Fearful traders should use smaller risk and clear invalidation.',
      if (personality == 'analytical')
        'Analytical traders should avoid delaying action after all plan conditions are met.',
      if (personality == 'patient' || personality == 'careful')
        'Patience is useful, but hesitation should not replace written rules.',
    ]);
  }

  String _lines(Iterable<String> lines) {
    return lines.where((line) => line.trim().isNotEmpty).join('\n');
  }
}
