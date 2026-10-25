import '../models/trading_strategy_recommendation.dart';

class TradingStrategyRecommendationService {
  const TradingStrategyRecommendationService();

  static const questionnaireVersion = '2026.07.strategy';

  static const sections = <TradingStrategyQuestionSection>[
    TradingStrategyQuestionSection(
      id: 'strategy_preference',
      label: 'Strategy Preference',
      questions: [
        TradingStrategyQuestion(
          id: 'technical_concept',
          label: 'Which technical concept do you prefer?',
          options: [
            TradingStrategyAnswerOption(key: 'snr', label: 'SNR'),
            TradingStrategyAnswerOption(key: 'smc', label: 'SMC'),
            TradingStrategyAnswerOption(key: 'ict', label: 'ICT'),
            TradingStrategyAnswerOption(
              key: 'market_structure',
              label: 'Market Structure',
            ),
            TradingStrategyAnswerOption(key: 'macd', label: 'MACD'),
            TradingStrategyAnswerOption(key: 'ema', label: 'EMA'),
            TradingStrategyAnswerOption(key: 'trendline', label: 'Trendline'),
            TradingStrategyAnswerOption(key: 'rsi', label: 'RSI'),
            TradingStrategyAnswerOption(key: 'hybrid', label: 'Hybrid'),
            TradingStrategyAnswerOption(key: 'not_sure', label: 'Not sure'),
          ],
        ),
        TradingStrategyQuestion(
          id: 'strategy_types',
          label: 'Which strategy types do you want to combine?',
          allowsMultiple: true,
          required: false,
          options: [
            TradingStrategyAnswerOption(key: 'snr', label: 'SNR'),
            TradingStrategyAnswerOption(key: 'smc', label: 'SMC'),
            TradingStrategyAnswerOption(key: 'ict', label: 'ICT'),
            TradingStrategyAnswerOption(key: 'ema', label: 'EMA'),
            TradingStrategyAnswerOption(key: 'macd', label: 'MACD'),
            TradingStrategyAnswerOption(key: 'rsi', label: 'RSI'),
            TradingStrategyAnswerOption(key: 'trendline', label: 'Trendline'),
            TradingStrategyAnswerOption(
              key: 'market_structure',
              label: 'Market Structure',
            ),
            TradingStrategyAnswerOption(
              key: 'breakout_retest',
              label: 'Breakout retest',
            ),
            TradingStrategyAnswerOption(
              key: 'pullback_continuation',
              label: 'Pullback continuation',
            ),
            TradingStrategyAnswerOption(
              key: 'range_rejection',
              label: 'Range rejection',
            ),
            TradingStrategyAnswerOption(key: 'hybrid', label: 'Hybrid'),
          ],
        ),
        TradingStrategyQuestion(
          id: 'setup_type',
          label: 'Which type of setup do you prefer?',
          options: [
            TradingStrategyAnswerOption(
              key: 'breakout_retest',
              label: 'Breakout retest',
            ),
            TradingStrategyAnswerOption(
              key: 'snr_rejection',
              label: 'Support and resistance rejection',
            ),
            TradingStrategyAnswerOption(
              key: 'pullback_continuation',
              label: 'Pullback continuation',
            ),
            TradingStrategyAnswerOption(
              key: 'order_block_reaction',
              label: 'Order block reaction',
            ),
            TradingStrategyAnswerOption(
              key: 'fvg_reaction',
              label: 'FVG reaction',
            ),
            TradingStrategyAnswerOption(
              key: 'trendline_rejection',
              label: 'Trendline rejection',
            ),
            TradingStrategyAnswerOption(
              key: 'ema_pullback',
              label: 'EMA pullback',
            ),
            TradingStrategyAnswerOption(
              key: 'indicator_crossover',
              label: 'Indicator crossover',
            ),
            TradingStrategyAnswerOption(
              key: 'reversal_setup',
              label: 'Reversal setup',
            ),
            TradingStrategyAnswerOption(key: 'not_sure', label: 'Not sure'),
          ],
        ),
        TradingStrategyQuestion(
          id: 'market_condition',
          label: 'Which market condition do you prefer?',
          allowsMultiple: true,
          options: [
            TradingStrategyAnswerOption(
              key: 'trending',
              label: 'Trending market',
            ),
            TradingStrategyAnswerOption(
              key: 'ranging',
              label: 'Ranging market',
            ),
            TradingStrategyAnswerOption(
              key: 'breakout',
              label: 'Breakout market',
            ),
            TradingStrategyAnswerOption(
              key: 'reversal',
              label: 'Reversal market',
            ),
            TradingStrategyAnswerOption(
              key: 'high_volatility',
              label: 'High volatility market',
            ),
            TradingStrategyAnswerOption(
              key: 'low_volatility',
              label: 'Low volatility market',
            ),
            TradingStrategyAnswerOption(
              key: 'news_driven',
              label: 'News-driven market',
            ),
            TradingStrategyAnswerOption(
              key: 'mixed',
              label: 'Mixed market conditions',
            ),
            TradingStrategyAnswerOption(key: 'not_sure', label: 'Not sure'),
          ],
        ),
      ],
    ),
    TradingStrategyQuestionSection(
      id: 'timeframe_execution',
      label: 'Timeframe and Execution',
      questions: [
        TradingStrategyQuestion(
          id: 'trading_style',
          label: 'Which trading style do you prefer?',
          options: [
            TradingStrategyAnswerOption(key: 'scalping', label: 'Scalping'),
            TradingStrategyAnswerOption(key: 'intraday', label: 'Intraday'),
            TradingStrategyAnswerOption(key: 'swing', label: 'Swing'),
            TradingStrategyAnswerOption(
              key: 'position',
              label: 'Position trading',
            ),
            TradingStrategyAnswerOption(key: 'not_sure', label: 'Not sure'),
          ],
        ),
        TradingStrategyQuestion(
          id: 'direction_timeframe',
          label: 'Which timeframe do you usually use for market direction?',
          options: [
            TradingStrategyAnswerOption(key: 'm15', label: 'M15'),
            TradingStrategyAnswerOption(key: 'h1', label: 'H1'),
            TradingStrategyAnswerOption(key: 'h4', label: 'H4'),
            TradingStrategyAnswerOption(key: 'daily', label: 'Daily'),
            TradingStrategyAnswerOption(key: 'not_sure', label: 'Not sure'),
          ],
        ),
        TradingStrategyQuestion(
          id: 'entry_timeframe',
          label: 'Which timeframe do you usually use for entry?',
          options: [
            TradingStrategyAnswerOption(key: 'm1', label: 'M1'),
            TradingStrategyAnswerOption(key: 'm5', label: 'M5'),
            TradingStrategyAnswerOption(key: 'm15', label: 'M15'),
            TradingStrategyAnswerOption(key: 'm30', label: 'M30'),
            TradingStrategyAnswerOption(key: 'h1', label: 'H1'),
            TradingStrategyAnswerOption(key: 'not_sure', label: 'Not sure'),
          ],
        ),
        TradingStrategyQuestion(
          id: 'multi_timeframe',
          label: 'Do you prefer multi-timeframe confirmation?',
          options: [
            TradingStrategyAnswerOption(key: 'yes', label: 'Yes'),
            TradingStrategyAnswerOption(key: 'no', label: 'No'),
            TradingStrategyAnswerOption(key: 'not_sure', label: 'Not sure'),
          ],
        ),
      ],
    ),
    TradingStrategyQuestionSection(
      id: 'entry_confirmation',
      label: 'Entry and Confirmation',
      questions: [
        TradingStrategyQuestion(
          id: 'confirmations',
          label: 'How many confirmations do you prefer before entry?',
          options: [
            TradingStrategyAnswerOption(key: 'one', label: '1 confirmation'),
            TradingStrategyAnswerOption(key: 'two', label: '2 confirmations'),
            TradingStrategyAnswerOption(key: 'three', label: '3 confirmations'),
            TradingStrategyAnswerOption(
              key: 'four_plus',
              label: '4 or more confirmations',
            ),
          ],
        ),
        TradingStrategyQuestion(
          id: 'trusted_confirmation',
          label: 'Which confirmation do you trust most?',
          options: [
            TradingStrategyAnswerOption(
              key: 'candle_rejection',
              label: 'Candle rejection',
            ),
            TradingStrategyAnswerOption(
              key: 'bos',
              label: 'Break of structure',
            ),
            TradingStrategyAnswerOption(key: 'choch', label: 'CHOCH'),
            TradingStrategyAnswerOption(key: 'retest', label: 'Retest'),
            TradingStrategyAnswerOption(key: 'volume', label: 'Volume'),
            TradingStrategyAnswerOption(
              key: 'ema_alignment',
              label: 'EMA alignment',
            ),
            TradingStrategyAnswerOption(
              key: 'macd_crossover',
              label: 'MACD crossover',
            ),
            TradingStrategyAnswerOption(
              key: 'rsi_divergence',
              label: 'RSI divergence',
            ),
            TradingStrategyAnswerOption(
              key: 'trendline_reaction',
              label: 'Trendline reaction',
            ),
            TradingStrategyAnswerOption(key: 'not_sure', label: 'Not sure'),
          ],
        ),
        TradingStrategyQuestion(
          id: 'entry_execution',
          label: 'How do you prefer to enter?',
          options: [
            TradingStrategyAnswerOption(
              key: 'market_execution',
              label: 'Market execution',
            ),
            TradingStrategyAnswerOption(
              key: 'limit_order',
              label: 'Limit order',
            ),
            TradingStrategyAnswerOption(key: 'stop_order', label: 'Stop order'),
            TradingStrategyAnswerOption(
              key: 'pending_after_retest',
              label: 'Pending order after retest',
            ),
            TradingStrategyAnswerOption(key: 'not_sure', label: 'Not sure'),
          ],
        ),
      ],
    ),
    TradingStrategyQuestionSection(
      id: 'exit_method',
      label: 'Exit Method',
      questions: [
        TradingStrategyQuestion(
          id: 'take_profit_method',
          label: 'How do you prefer to take profit?',
          options: [
            TradingStrategyAnswerOption(
              key: 'fixed_rr',
              label: 'Fixed risk-reward',
            ),
            TradingStrategyAnswerOption(
              key: 'previous_high_low',
              label: 'Previous high/low',
            ),
            TradingStrategyAnswerOption(
              key: 'snr_level',
              label: 'Support/resistance level',
            ),
            TradingStrategyAnswerOption(
              key: 'partial_close',
              label: 'Partial close',
            ),
            TradingStrategyAnswerOption(
              key: 'trailing_stop',
              label: 'Trailing stop',
            ),
            TradingStrategyAnswerOption(
              key: 'indicator_exit',
              label: 'Indicator exit',
            ),
            TradingStrategyAnswerOption(key: 'not_sure', label: 'Not sure'),
          ],
        ),
        TradingStrategyQuestion(
          id: 'stop_loss_method',
          label: 'How do you prefer to place stop loss?',
          options: [
            TradingStrategyAnswerOption(
              key: 'structure',
              label: 'Below/above structure',
            ),
            TradingStrategyAnswerOption(
              key: 'snr_zone',
              label: 'Below/above SNR zone',
            ),
            TradingStrategyAnswerOption(key: 'fixed_pips', label: 'Fixed pips'),
            TradingStrategyAnswerOption(key: 'atr_based', label: 'ATR-based'),
            TradingStrategyAnswerOption(
              key: 'previous_candle',
              label: 'Previous candle high/low',
            ),
            TradingStrategyAnswerOption(key: 'not_sure', label: 'Not sure'),
          ],
        ),
      ],
    ),
  ];

  TradingStrategyRecommendationResult recommend(
    TradingStrategyQuestionnaireResponse response,
  ) {
    final concept = _answer(response, 'technical_concept');
    final setup = _answer(response, 'setup_type');
    final marketCondition = _answer(response, 'market_condition');
    final style = _answer(response, 'trading_style');
    final directionTimeframe = _answer(response, 'direction_timeframe');
    final entryTimeframe = _answer(response, 'entry_timeframe');
    final multiTimeframe = _answer(response, 'multi_timeframe');
    final confirmations = _answer(response, 'confirmations');
    final trustedConfirmation = _answer(response, 'trusted_confirmation');
    final execution = _answer(response, 'entry_execution');
    final takeProfit = _answer(response, 'take_profit_method');
    final stopLoss = _answer(response, 'stop_loss_method');

    final selectedStrategyKeys = _selectedStrategyKeys(
      response,
      concept: concept,
      setup: setup,
    );
    final marketConditions = _selectedMarketConditionKeys(
      response,
      fallback: marketCondition,
    );
    final primaryConcept = concept != 'not_sure' && concept.isNotEmpty
        ? concept
        : selectedStrategyKeys.isEmpty
        ? 'hybrid'
        : selectedStrategyKeys.first;
    final family = _strategyFamily(primaryConcept);
    final mixedPlaybook =
        selectedStrategyKeys.length > 1 ||
        marketConditions.length > 1 ||
        marketConditions.contains('mixed');
    final selectedStrategyLabels = [
      for (final key in selectedStrategyKeys) _strategyTypeLabel(key),
    ];
    final selectedMarketLabels = _selectedMarketConditionLabels(
      marketConditions,
      family,
    );
    final recommendedStyle = _styleLabel(style);
    final recommendedDirectionTimeframe = _directionTimeframe(
      directionTimeframe,
      style: recommendedStyle,
      family: family,
    );
    final recommendedEntryTimeframe = _entryTimeframe(
      entryTimeframe,
      style: recommendedStyle,
      family: family,
    );

    return TradingStrategyRecommendationResult(
      questionnaireVersion: questionnaireVersion,
      strategyName: _strategyName(
        family,
        setup,
        selectedStrategyKeys: selectedStrategyKeys,
        marketConditions: marketConditions,
      ),
      strategyCategory: _strategyCategory(
        family,
        selectedStrategyKeys: selectedStrategyKeys,
      ),
      technicalConcept: _technicalConcept(
        family,
        primaryConcept,
        selectedStrategyKeys: selectedStrategyKeys,
      ),
      suitableMarketCondition: selectedMarketLabels.isEmpty
          ? _marketConditionLabel(marketCondition, family)
          : selectedMarketLabels.join(', '),
      recommendedTradingStyle: recommendedStyle,
      recommendedDirectionTimeframe: recommendedDirectionTimeframe,
      recommendedEntryTimeframe: recommendedEntryTimeframe,
      multiTimeframeConfirmationRule: _multiTimeframeRule(
        multiTimeframe,
        recommendedDirectionTimeframe,
        recommendedEntryTimeframe,
      ),
      analysisMethods: _analysisMethods(
        family,
        primaryConcept,
        selectedStrategyKeys: selectedStrategyKeys,
      ),
      entryModel: _entryModel(family, setup, execution),
      entryConfirmationRules: _lines([
        ..._familyConfirmationRules(family),
        _confirmationRule(trustedConfirmation),
        _confirmationCountRule(confirmations),
        if (mixedPlaybook)
          'Use one strategy playbook per trade and do not mix triggers after entry is selected.',
      ]),
      invalidSetupConditions: _lines([
        ..._familyInvalidRules(family),
        if (marketConditions.contains('ranging') &&
            family == _StrategyFamily.indicator)
          'Invalid when price is choppy and indicators flip repeatedly.',
        if (marketConditions.contains('low_volatility'))
          'Invalid when there is not enough movement to reach the planned target.',
        if (marketConditions.contains('high_volatility'))
          'Invalid when spread, candle speed, or slippage prevents a clean stop-loss placement.',
        if (marketConditions.contains('news_driven'))
          'Invalid within the restricted news window unless news trading is part of the tested strategy.',
      ]),
      stopLossPlacementRule: _stopLossRule(family, stopLoss),
      takeProfitRule: _takeProfitRule(family, takeProfit),
      tradeManagementRule: _tradeManagementRule(takeProfit),
      exampleSetupChecklist: _lines([
        if (mixedPlaybook)
          'Current market condition matches the chosen strategy playbook.',
        'Direction timeframe is aligned.',
        'Entry timeframe setup is present.',
        _confirmationRule(trustedConfirmation),
        'Stop loss and take profit are defined before entry.',
        'Invalid setup conditions are not present.',
      ]),
      suitableTraderType: _suitableTraderType(family, recommendedStyle),
      strategyWarning: _strategyWarning(family, mixedPlaybook: mixedPlaybook),
      recommendedPracticeNotes: _lines([
        'Practice this strategy on at least 20 screenshots before changing rules.',
        if (mixedPlaybook)
          'Track each market condition separately so trending, ranging, breakout, and reversal results are not mixed together.',
        'Record the exact setup type, confirmation, SL placement, and TP method.',
        'Review only this technical method separately from psychology and risk rules.',
      ]),
      selectedStrategyTypes: selectedStrategyLabels,
      selectedMarketConditions: selectedMarketLabels,
      recommendedStrategySet: _recommendedStrategySet(
        family,
        selectedStrategyKeys,
        marketConditions,
      ),
      marketConditionRules: _marketConditionRules(marketConditions),
      riskReducedStrategyRules: _riskReducedStrategyRules(
        marketConditions,
        selectedStrategyKeys,
      ),
    );
  }

  String _answer(
    TradingStrategyQuestionnaireResponse response,
    String questionId,
  ) {
    return response.answerFor(questionId) ?? '';
  }

  List<String> _selectedStrategyKeys(
    TradingStrategyQuestionnaireResponse response, {
    required String concept,
    required String setup,
  }) {
    return _unique([
      ...response.answersFor('strategy_types'),
      if (concept.isNotEmpty && concept != 'not_sure') concept,
      if (concept == 'not_sure' && setup.isNotEmpty && setup != 'not_sure')
        setup,
    ]).where((key) => key != 'not_sure').toList(growable: false);
  }

  List<String> _selectedMarketConditionKeys(
    TradingStrategyQuestionnaireResponse response, {
    required String fallback,
  }) {
    return _unique([
      ...response.answersFor('market_condition'),
      if (fallback.isNotEmpty) fallback,
    ]).where((key) => key != 'not_sure').toList(growable: false);
  }

  List<String> _selectedMarketConditionLabels(
    List<String> marketConditions,
    _StrategyFamily family,
  ) {
    if (marketConditions.isEmpty) return [_marketConditionLabel('', family)];
    return [
      for (final condition in marketConditions)
        _marketConditionOptionLabel(condition),
    ];
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

  _StrategyFamily _strategyFamily(String concept) {
    return switch (concept) {
      'snr' => _StrategyFamily.snr,
      'smc' ||
      'ict' ||
      'order_block_reaction' ||
      'fvg_reaction' => _StrategyFamily.smcIct,
      'ema' || 'macd' || 'rsi' => _StrategyFamily.indicator,
      'trendline' || 'trendline_rejection' => _StrategyFamily.trendline,
      'market_structure' ||
      'breakout_retest' ||
      'pullback_continuation' => _StrategyFamily.marketStructure,
      'range_rejection' || 'snr_rejection' => _StrategyFamily.snr,
      _ => _StrategyFamily.hybrid,
    };
  }

  String _strategyName(
    _StrategyFamily family,
    String setup, {
    required List<String> selectedStrategyKeys,
    required List<String> marketConditions,
  }) {
    if (selectedStrategyKeys.length > 1 || marketConditions.length > 1) {
      return 'Mixed Market Multi-Strategy Framework';
    }
    return switch (family) {
      _StrategyFamily.snr =>
        setup == 'breakout_retest'
            ? 'Support and Resistance Breakout Retest Strategy'
            : 'Support and Resistance Rejection Strategy',
      _StrategyFamily.smcIct => 'SMC/ICT Structure Liquidity POI Strategy',
      _StrategyFamily.indicator => 'Trend Continuation Indicator Strategy',
      _StrategyFamily.trendline =>
        'Trendline Rejection / Breakout Retest Strategy',
      _StrategyFamily.marketStructure =>
        'Market Structure Continuation Strategy',
      _StrategyFamily.hybrid => 'Hybrid Technical Confirmation Strategy',
    };
  }

  String _strategyCategory(
    _StrategyFamily family, {
    required List<String> selectedStrategyKeys,
  }) {
    if (selectedStrategyKeys.length > 1) {
      return 'Hybrid Multi-Strategy Framework';
    }
    return switch (family) {
      _StrategyFamily.snr => 'SNR Strategy',
      _StrategyFamily.smcIct => 'SMC / ICT Strategy',
      _StrategyFamily.indicator => 'Indicator Trend Strategy',
      _StrategyFamily.trendline => 'Trendline Strategy',
      _StrategyFamily.marketStructure => 'Market Structure Strategy',
      _StrategyFamily.hybrid => 'Hybrid Strategy',
    };
  }

  String _technicalConcept(
    _StrategyFamily family,
    String concept, {
    required List<String> selectedStrategyKeys,
  }) {
    final primary = switch (family) {
      _StrategyFamily.snr =>
        'Support and resistance zones with rejection or retest confirmation',
      _StrategyFamily.smcIct =>
        'Market structure, liquidity, order block, and FVG reaction',
      _StrategyFamily.indicator =>
        concept == 'macd'
            ? 'MACD momentum with trend continuation confirmation'
            : concept == 'rsi'
            ? 'RSI confirmation with structure context'
            : 'EMA alignment with price-action pullback',
      _StrategyFamily.trendline =>
        'Trendline reaction, invalidation, and breakout retest',
      _StrategyFamily.marketStructure =>
        'Market structure continuation, BOS, CHOCH, and retest',
      _StrategyFamily.hybrid =>
        'Hybrid level, structure, and confirmation-based execution',
    };
    if (selectedStrategyKeys.length <= 1) return primary;
    final supporting = selectedStrategyKeys
        .map(_strategyTypeLabel)
        .where((label) => label.trim().isNotEmpty)
        .join(', ');
    return '$primary\nSupporting methods: $supporting';
  }

  String _marketConditionLabel(String condition, _StrategyFamily family) {
    if (condition != 'not_sure' && condition.isNotEmpty) {
      return _marketConditionOptionLabel(condition);
    }
    return switch (family) {
      _StrategyFamily.snr => 'Ranging or breakout market near key levels',
      _StrategyFamily.smcIct =>
        'Trending or reversal market with liquidity displacement',
      _StrategyFamily.indicator => 'Trending market with clean momentum',
      _StrategyFamily.trendline =>
        'Trending market with clear trendline reactions',
      _ => 'Trending or structured market',
    };
  }

  String _marketConditionOptionLabel(String condition) {
    return switch (condition) {
      'trending' => 'Trending market',
      'ranging' => 'Ranging market',
      'breakout' => 'Breakout market',
      'reversal' => 'Reversal market',
      'high_volatility' => 'High volatility market',
      'low_volatility' => 'Low volatility market',
      'news_driven' => 'News-driven market',
      'mixed' => 'Mixed market conditions',
      _ => 'Hybrid market',
    };
  }

  String _styleLabel(String style) {
    return switch (style) {
      'scalping' => 'Scalping',
      'swing' => 'Swing',
      'position' => 'Position Trading',
      _ => 'Intraday',
    };
  }

  String _directionTimeframe(
    String selected, {
    required String style,
    required _StrategyFamily family,
  }) {
    if (selected != 'not_sure' && selected.isNotEmpty) {
      return selected.toUpperCase();
    }
    if (style == 'Scalping') return 'M15';
    if (style == 'Swing' || style == 'Position Trading') return 'H4/Daily';
    if (family == _StrategyFamily.smcIct) return 'H4/H1';
    return 'H1/H4';
  }

  String _entryTimeframe(
    String selected, {
    required String style,
    required _StrategyFamily family,
  }) {
    if (selected != 'not_sure' && selected.isNotEmpty) {
      return selected.toUpperCase();
    }
    if (style == 'Scalping') return 'M5';
    if (style == 'Swing' || style == 'Position Trading') return 'H1';
    if (family == _StrategyFamily.smcIct) return 'M15/M5';
    return 'M15/M5';
  }

  String _multiTimeframeRule(String selected, String direction, String entry) {
    if (selected == 'no') {
      return 'Use one clear timeframe only, but do not enter against obvious higher-timeframe structure.';
    }
    return 'Confirm market direction on $direction, then execute only after the setup appears on $entry.';
  }

  List<String> _analysisMethods(
    _StrategyFamily family,
    String concept, {
    required List<String> selectedStrategyKeys,
  }) {
    final base = switch (family) {
      _StrategyFamily.snr => ['SNR', 'Market Structure', 'Price Action'],
      _StrategyFamily.smcIct => [
        'SMC',
        'ICT',
        'Market Structure',
        'Order Block',
        'FVG',
        'Liquidity',
      ],
      _StrategyFamily.indicator => [
        if (concept == 'macd')
          'MACD'
        else if (concept == 'rsi')
          'RSI'
        else
          'EMA',
        'Market Structure',
        'Price Action',
      ],
      _StrategyFamily.trendline => [
        'Trendline',
        'Market Structure',
        'Price Action',
      ],
      _StrategyFamily.marketStructure => ['Market Structure', 'Price Action'],
      _StrategyFamily.hybrid => ['SNR', 'Market Structure', 'Price Action'],
    };
    return _unique([
      ...base,
      for (final key in selectedStrategyKeys) _strategyTypeLabel(key),
    ]);
  }

  String _strategyTypeLabel(String key) {
    return switch (key) {
      'snr' => 'SNR',
      'smc' => 'SMC',
      'ict' => 'ICT',
      'ema' => 'EMA',
      'macd' => 'MACD',
      'rsi' => 'RSI',
      'trendline' => 'Trendline',
      'market_structure' => 'Market Structure',
      'breakout_retest' => 'Breakout Retest',
      'pullback_continuation' => 'Pullback Continuation',
      'range_rejection' => 'Range Rejection',
      'snr_rejection' => 'SNR Rejection',
      'order_block_reaction' => 'Order Block Reaction',
      'fvg_reaction' => 'FVG Reaction',
      'trendline_rejection' => 'Trendline Rejection',
      'ema_pullback' => 'EMA Pullback',
      'indicator_crossover' => 'Indicator Crossover',
      'reversal_setup' => 'Reversal Setup',
      'hybrid' => 'Hybrid',
      _ => 'Hybrid',
    };
  }

  String _entryModel(_StrategyFamily family, String setup, String execution) {
    final executionText = switch (execution) {
      'market_execution' => 'market execution',
      'limit_order' => 'limit order',
      'stop_order' => 'stop order',
      'pending_after_retest' => 'pending order after retest',
      _ => 'planned execution',
    };
    final setupText = switch (setup) {
      'breakout_retest' => 'breakout retest',
      'snr_rejection' => 'support/resistance rejection',
      'pullback_continuation' => 'pullback continuation',
      'order_block_reaction' => 'order block reaction',
      'fvg_reaction' => 'FVG reaction',
      'trendline_rejection' => 'trendline rejection',
      'ema_pullback' => 'EMA pullback',
      'indicator_crossover' => 'indicator crossover',
      'reversal_setup' => 'reversal setup',
      _ => switch (family) {
        _StrategyFamily.snr => 'SNR rejection or breakout retest',
        _StrategyFamily.smcIct =>
          'POI reaction after liquidity and structure confirmation',
        _StrategyFamily.indicator =>
          'trend pullback with indicator confirmation',
        _StrategyFamily.trendline => 'trendline rejection or breakout retest',
        _ => 'structured technical setup',
      },
    };
    return 'Wait for $setupText, then enter with $executionText.';
  }

  Iterable<String> _familyConfirmationRules(_StrategyFamily family) {
    return switch (family) {
      _StrategyFamily.snr => [
        'Price must react at a marked support or resistance zone.',
        'Entry should use rejection candle or retest confirmation.',
      ],
      _StrategyFamily.smcIct => [
        'Higher-timeframe bias must be clear before selecting POI.',
        'Require BOS/CHOCH, liquidity sweep, displacement, or POI reaction.',
      ],
      _StrategyFamily.indicator => [
        'Trend direction must agree with the indicator signal.',
        'Do not enter from the indicator alone without price structure.',
      ],
      _StrategyFamily.trendline => [
        'Trendline must have clear valid touches.',
        'Entry requires reaction candle or breakout retest.',
      ],
      _ => [
        'Market structure must support the direction.',
        'Entry must have a clear technical trigger.',
      ],
    };
  }

  String _confirmationRule(String confirmation) {
    return switch (confirmation) {
      'candle_rejection' => 'Primary confirmation is candle rejection.',
      'bos' => 'Primary confirmation is break of structure.',
      'choch' => 'Primary confirmation is CHOCH.',
      'retest' => 'Primary confirmation is retest.',
      'volume' => 'Primary confirmation is volume expansion.',
      'ema_alignment' => 'Primary confirmation is EMA alignment.',
      'macd_crossover' => 'Primary confirmation is MACD crossover.',
      'rsi_divergence' => 'Primary confirmation is RSI divergence.',
      'trendline_reaction' => 'Primary confirmation is trendline reaction.',
      _ => 'Use one clear confirmation that matches the strategy.',
    };
  }

  String _confirmationCountRule(String confirmations) {
    return switch (confirmations) {
      'one' =>
        'Use 1 strong confirmation only when the setup location is high quality.',
      'two' => 'Use at least 2 confirmations before entry.',
      'three' => 'Use 3 confirmations before entry.',
      _ => 'Use 4 or more confirmations and skip partial setups.',
    };
  }

  Iterable<String> _familyInvalidRules(_StrategyFamily family) {
    return switch (family) {
      _StrategyFamily.snr => [
        'Invalid if price is in the middle of the range.',
        'Invalid if the support/resistance zone is not clearly marked.',
      ],
      _StrategyFamily.smcIct => [
        'Invalid if POI is forced without structure confirmation.',
        'Invalid if there is no liquidity sweep, displacement, BOS, or CHOCH.',
      ],
      _StrategyFamily.indicator => [
        'Invalid in choppy ranging conditions.',
        'Invalid when indicator signal disagrees with market structure.',
      ],
      _StrategyFamily.trendline => [
        'Invalid if the trendline has weak or forced touches.',
        'Invalid if price breaks the trendline without retest confirmation.',
      ],
      _ => [
        'Invalid when direction, setup location, and confirmation do not align.',
      ],
    };
  }

  String _stopLossRule(_StrategyFamily family, String selected) {
    if (selected != 'not_sure' && selected.isNotEmpty) {
      return switch (selected) {
        'structure' => 'Place SL below/above the invalidating structure.',
        'snr_zone' => 'Place SL outside the support/resistance zone.',
        'fixed_pips' =>
          'Use fixed pips only if it still sits beyond technical invalidation.',
        'atr_based' => 'Use ATR-based SL with structure confirmation.',
        'previous_candle' =>
          'Place SL beyond the previous candle high/low if it matches invalidation.',
        _ => 'Place SL beyond technical invalidation.',
      };
    }
    return switch (family) {
      _StrategyFamily.snr => 'Place SL outside the SNR zone.',
      _StrategyFamily.smcIct =>
        'Place SL beyond the invalidation high/low of the POI.',
      _StrategyFamily.indicator =>
        'Place SL beyond structure or use ATR-based invalidation.',
      _StrategyFamily.trendline => 'Place SL beyond trendline invalidation.',
      _ => 'Place SL beyond the technical invalidation point.',
    };
  }

  String _takeProfitRule(_StrategyFamily family, String selected) {
    if (selected != 'not_sure' && selected.isNotEmpty) {
      return switch (selected) {
        'fixed_rr' =>
          'Take profit at fixed risk-reward, preferably minimum 1:2.',
        'previous_high_low' => 'Take profit at previous high/low.',
        'snr_level' => 'Take profit at the next support/resistance level.',
        'partial_close' => 'Partial close only at planned levels.',
        'trailing_stop' =>
          'Use trailing stop only after price confirms continuation.',
        'indicator_exit' =>
          'Exit when indicator momentum fails and structure agrees.',
        _ => 'Take profit at planned technical target.',
      };
    }
    return switch (family) {
      _StrategyFamily.snr =>
        'Take profit at next key level or minimum planned RR.',
      _StrategyFamily.smcIct =>
        'Take profit at liquidity target or structure target.',
      _StrategyFamily.indicator => 'Take profit at fixed RR or next structure.',
      _StrategyFamily.trendline => 'Take profit at next structure level.',
      _ => 'Take profit at planned structure, liquidity, or RR target.',
    };
  }

  String _tradeManagementRule(String takeProfit) {
    if (takeProfit == 'partial_close') {
      return 'Partial close is allowed only when planned before entry; protect the remaining position.';
    }
    if (takeProfit == 'trailing_stop') {
      return 'Trail only after price reaches the planned management area.';
    }
    return 'Do not move SL/TP emotionally; manage only by the technical plan.';
  }

  String _suitableTraderType(_StrategyFamily family, String style) {
    return switch (family) {
      _StrategyFamily.snr =>
        'Suitable for traders who can wait for price to reach key levels.',
      _StrategyFamily.smcIct =>
        'Suitable for traders who can read structure, liquidity, and POI context.',
      _StrategyFamily.indicator =>
        'Suitable for traders who prefer rule-based trend confirmation.',
      _StrategyFamily.trendline =>
        'Suitable for traders who can validate trendline quality objectively.',
      _ => 'Suitable for traders who want a structured technical method.',
    };
  }

  String _recommendedStrategySet(
    _StrategyFamily family,
    List<String> selectedStrategyKeys,
    List<String> marketConditions,
  ) {
    final strategyNames = selectedStrategyKeys.isEmpty
        ? [_strategyCategory(family, selectedStrategyKeys: const [])]
        : [for (final key in selectedStrategyKeys) _strategyTypeLabel(key)];
    final lines = <String>[
      'Primary technical method: ${strategyNames.join(', ')}.',
      if (marketConditions.contains('trending') ||
          marketConditions.contains('mixed'))
        'Trending Market Strategy: trade continuation only when higher-timeframe structure and momentum agree.',
      if (marketConditions.contains('ranging') ||
          marketConditions.contains('mixed'))
        'Ranging Market Strategy: trade only at marked support/resistance extremes and skip the middle of the range.',
      if (marketConditions.contains('breakout') ||
          marketConditions.contains('mixed'))
        'Breakout Market Strategy: wait for displacement and retest before entry; avoid chasing the breakout candle.',
      if (marketConditions.contains('reversal') ||
          marketConditions.contains('mixed'))
        'Reversal Market Strategy: require a structure shift such as CHOCH/BOS before taking the reversal.',
      if (marketConditions.contains('high_volatility'))
        'High Volatility Rule: reduce position size, widen only technically valid stops, and skip fast candles with poor fills.',
      if (marketConditions.contains('low_volatility'))
        'Low Volatility Rule: avoid trades when the available range cannot reach the planned target.',
      if (marketConditions.contains('news_driven'))
        'News-Driven Market Rule: no entry inside the restricted news window unless this exact setup has been tested.',
    ];
    if (lines.length == 1) {
      lines.add(
        'Single Market Strategy: take only the setup type selected in this questionnaire and keep the rule set unchanged.',
      );
    }
    return _lines(lines);
  }

  String _marketConditionRules(List<String> marketConditions) {
    if (marketConditions.isEmpty) {
      return 'Use the strategy only when the market condition is clear before entry.';
    }
    return _lines([
      if (marketConditions.length > 1 || marketConditions.contains('mixed'))
        'Before each trade, choose the active market condition first; the condition decides which setup is allowed.',
      if (marketConditions.contains('trending'))
        'Trending condition: direction timeframe must show higher highs/higher lows or lower highs/lower lows.',
      if (marketConditions.contains('ranging'))
        'Ranging condition: only trade reactions at the range boundary; no entries in the middle.',
      if (marketConditions.contains('breakout'))
        'Breakout condition: require breakout, acceptance, and retest before entry.',
      if (marketConditions.contains('reversal'))
        'Reversal condition: require exhaustion plus structure shift before entry.',
      if (marketConditions.contains('high_volatility'))
        'High volatility condition: skip if spread, slippage, or candle speed makes risk unclear.',
      if (marketConditions.contains('low_volatility'))
        'Low volatility condition: skip if the target is too close or momentum is absent.',
      if (marketConditions.contains('news_driven'))
        'News condition: check the economic calendar and avoid unplanned news entries.',
    ]);
  }

  String _riskReducedStrategyRules(
    List<String> marketConditions,
    List<String> selectedStrategyKeys,
  ) {
    return _lines([
      if (selectedStrategyKeys.length > 1)
        'When more than one strategy qualifies, choose the cleanest setup only; do not stack extra trades.',
      if (marketConditions.contains('high_volatility') ||
          marketConditions.contains('news_driven'))
        'Use reduced risk in high-impact or fast-moving conditions.',
      'If the setup requires interpretation after entry, skip it before entry.',
    ]);
  }

  String _strategyWarning(
    _StrategyFamily family, {
    required bool mixedPlaybook,
  }) {
    final warning = switch (family) {
      _StrategyFamily.snr => 'Do not enter in the middle of a range.',
      _StrategyFamily.smcIct =>
        'Avoid forcing POI entries without structure confirmation.',
      _StrategyFamily.indicator =>
        'Avoid using indicator strategy in choppy range.',
      _StrategyFamily.trendline => 'Do not force trendlines with weak touches.',
      _StrategyFamily.marketStructure =>
        'Do not trade structure labels without a clear trigger.',
      _StrategyFamily.hybrid =>
        'Do not combine too many signals until rules are proven.',
    };
    if (!mixedPlaybook) return warning;
    return _lines([
      warning,
      'Mixed-market strategies fail when the trader changes playbooks after seeing price move.',
    ]);
  }

  String _lines(Iterable<String> lines) {
    return lines.where((line) => line.trim().isNotEmpty).join('\n');
  }
}

enum _StrategyFamily {
  snr,
  smcIct,
  indicator,
  trendline,
  marketStructure,
  hybrid,
}
