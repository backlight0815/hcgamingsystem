<?php

namespace App\Services;

use App\Models\FinalExamQuestion;
use RuntimeException;

class FinalExamQuestionBankFactory
{
    public function questions(): array
    {
        $questions = array_merge(
            $this->foundationTextQuestions(),
            $this->techniqueTextQuestions(),
            $this->techniqueChartQuestions(),
            $this->psychologyChartScenarioQuestions(),
        );

        $this->assertValidBank($questions);

        return $questions;
    }

    private function foundationTextQuestions(): array
    {
        $topics = [
            ['Forex Trading', 'Market Basics', 'Buying one currency while selling another to profit from exchange-rate movement.', 'check pair direction, spread, session, and risk before entering'],
            ['Gold / XAUUSD', 'Market Basics', 'Spot gold priced against the US dollar.', 'respect gold volatility and calculate lot size before entry'],
            ['Lot Size', 'Position Sizing', 'The volume of a trade position.', 'match position volume to risk percentage and stop distance'],
            ['Leverage', 'Margin', 'Borrowed exposure that lets smaller margin control a larger position.', 'keep risk small because profit and loss scale with exposure'],
            ['Margin', 'Margin', 'Capital held by the broker to support an open leveraged position.', 'keep enough free margin before adding positions'],
            ['Equity', 'Account Metrics', 'Balance plus or minus current floating profit and loss.', 'monitor live account value while positions are open'],
            ['Balance', 'Account Metrics', 'Closed account value excluding floating profit and loss.', 'compare it with equity to see open-trade impact'],
            ['Floating Profit/Loss', 'Account Metrics', 'Unrealized gain or loss from open trades.', 'avoid treating open profit as final before closing'],
            ['Spread', 'Execution', 'The difference between bid and ask price.', 'include it when planning entries, stops, and scalps'],
            ['Swap', 'Execution', 'Overnight financing charge or credit for holding a position.', 'check rollover cost before holding overnight'],
            ['Slippage', 'Execution', 'The difference between expected price and actual fill price.', 'expect worse fills during news and fast markets'],
            ['Fundamental Analysis', 'Analysis Types', 'Studying economic data, central banks, and macro drivers.', 'check high-impact news before technical entries'],
            ['Technical Analysis', 'Analysis Types', 'Reading charts, structure, levels, indicators, and price action.', 'combine chart evidence with invalidation and risk'],
            ['Market Session', 'Market Basics', 'A trading period such as Asian, London, or New York hours.', 'adjust expectations because volatility changes by session'],
            ['Liquidity', 'Market Basics', 'How easily price can trade size without major disruption.', 'prefer cleaner execution around liquid sessions and levels'],
            ['Volatility', 'Market Basics', 'The speed and size of price movement.', 'reduce lot size or widen plans when ranges expand'],
            ['Risk Management', 'Risk Management', 'Rules controlling how much capital is exposed to loss.', 'define max loss before entry'],
            ['Capital Management', 'Risk Management', 'Managing account survival, allocation, and drawdown.', 'protect capital before chasing returns'],
            ['Drawdown', 'Performance Metrics', 'A decline from account peak to a lower value.', 'reduce risk when drawdown breaches the plan'],
            ['Win Rate', 'Performance Metrics', 'The percentage of trades that close profitably.', 'judge it together with reward-to-risk'],
            ['Risk-to-Reward Ratio', 'Performance Metrics', 'Potential reward divided by potential loss.', 'skip trades where target does not justify risk'],
            ['Risk Per Trade', 'Position Sizing', 'The amount or percentage intentionally exposed on one idea.', 'calculate maximum planned loss before lot size'],
            ['Gold Pips', 'Gold Trading', 'Gold price movement units based on broker quote format.', 'verify point value before sizing XAUUSD'],
            ['Gold Profit/Loss', 'Gold Trading', 'Money gained or lost from XAUUSD movement, lot size, and contract value.', 'estimate loss from entry to stop before trading'],
            ['Take Profit', 'Orders', 'A planned exit at the profit target.', 'place targets near realistic liquidity or structure'],
            ['Stop Loss', 'Orders', 'A planned exit that limits loss when the idea is invalidated.', 'place stops beyond invalidation, not randomly'],
            ['Breakeven', 'Trade Management', 'Moving stop near entry so remaining loss risk is small or zero.', 'move BE only when the setup has earned protection'],
            ['Market Order', 'Orders', 'An order intended to execute immediately.', 'use it only when immediate entry is part of the plan'],
            ['Limit Order', 'Orders', 'An order to buy lower or sell higher than current price.', 'use it for planned pullback entries'],
            ['Stop Order', 'Orders', 'An order that triggers after price trades through a level.', 'use it for confirmed momentum entries'],
            ['Buy Limit', 'Orders', 'A pending buy order below current price.', 'use it when waiting for support pullback'],
            ['Sell Limit', 'Orders', 'A pending sell order above current price.', 'use it when waiting for resistance pullback'],
            ['Buy Stop', 'Orders', 'A pending buy order above current price.', 'use it when buying only after breakout confirmation'],
            ['Sell Stop', 'Orders', 'A pending sell order below current price.', 'use it when selling only after breakdown confirmation'],
            ['Pending Order', 'Orders', 'An order waiting for price conditions before execution.', 'predefine entry, stop, and target before placing it'],
            ['Partial Close', 'Trade Management', 'Closing part of a position while leaving the rest open.', 'lock some profit while allowing continuation'],
            ['Manual Close', 'Trade Management', 'Closing directly before TP or SL executes.', 'use it only when the trade reason becomes invalid'],
            ['Preset TP/SL', 'Trade Discipline', 'Target and stop levels planned before or at entry.', 'avoid emotional changes after entry'],
            ['Free Margin', 'Margin', 'Equity not tied up as required margin.', 'check it before adding exposure'],
            ['Margin Call', 'Margin', 'A broker warning or action when equity is too low for margin.', 'reduce exposure before the account is forced'],
            ['Commission', 'Execution', 'A transaction fee charged by the broker.', 'include it when reviewing net performance'],
            ['Account Currency', 'Account Metrics', 'The currency used for account balance and P/L.', 'convert risk correctly when instruments quote differently'],
            ['Liquidity Sweep', 'Market Basics', 'A move through obvious highs/lows before returning into range.', 'wait for confirmation instead of chasing the wick'],
            ['Break Even Trade', 'Trade Management', 'A trade closed near entry with little or no gain/loss.', 'record it separately from normal wins and losses'],
            ['Trading Journal', 'Review', 'A structured record of setup, execution, emotion, and lesson.', 'use it to find repeated mistakes'],
            ['High Impact News', 'Risk Filters', 'Scheduled events that can increase volatility and slippage.', 'avoid fresh trades if the plan excludes news risk'],
            ['No-Trade Condition', 'Risk Filters', 'A market state where the plan says not to enter.', 'protect discipline by accepting no-trade decisions'],
            ['Prop Firm Daily Loss', 'Prop Firm Rules', 'The maximum loss allowed for a trading day.', 'stop trading before breaching the rule'],
            ['Prop Firm Max Loss', 'Prop Firm Rules', 'The maximum total drawdown allowed by the account rules.', 'size positions so one idea cannot breach the account'],
            ['Following Trading Plan', 'Trade Discipline', 'Executing only the setups, risk, and exits defined beforehand.', 'measure success by process, not only money'],
        ];

        $questions = [];
        foreach ($topics as $index => [$term, $subcategory, $definition, $application]) {
            $difficulty = $this->difficultyByIndex($index);
            $questions[] = $this->q(FinalExamQuestion::CATEGORY_FOUNDATION, $subcategory, FinalExamQuestion::TYPE_TEXT_MCQ, 'Foundation definition: what does '.$term.' mean in trading?', $definition, [
                'A guaranteed signal that the next trade will win.',
                'A broker-only setting that does not affect risk.',
                'A chart decoration that should be ignored.',
            ], $term.' is a basic trading concept that affects planning, execution, or review.', $difficulty);
            $questions[] = $this->q(FinalExamQuestion::CATEGORY_FOUNDATION, $subcategory, FinalExamQuestion::TYPE_TEXT_MCQ, 'Foundation application: how should a trader use '.$term.'?', 'They '.$application.'.', [
                'They increase lot size first and decide risk later.',
                'They ignore it because only final profit matters.',
                'They remove the stop loss to give the trade room.',
            ], 'The practical use of '.$term.' should improve decision quality before risk is committed.', $difficulty);
            $questions[] = $this->q(FinalExamQuestion::CATEGORY_FOUNDATION, $subcategory, FinalExamQuestion::TYPE_TEXT_MCQ, 'Foundation risk check: what is the common mistake when handling '.$term.'?', 'Treating it emotionally instead of using it inside a written risk plan.', [
                'Recording it in the trading journal after the trade.',
                'Checking it before choosing position size.',
                'Using it to decide whether the trade is valid.',
            ], 'Foundation knowledge becomes useful only when it supports a consistent process.', $this->difficultyByIndex($index + 1));
        }

        return $this->takeExact($questions, 150, FinalExamQuestion::CATEGORY_FOUNDATION);
    }

    private function techniqueTextQuestions(): array
    {
        $topics = [
            ['Higher High', 'Market Structure', 'A swing high above the previous meaningful high.', 'bullish structure is progressing if higher lows also hold'],
            ['Higher Low', 'Market Structure', 'A pullback low above the previous meaningful low.', 'bullish continuation remains valid while it holds'],
            ['Lower High', 'Market Structure', 'A rally high below the previous meaningful high.', 'bearish continuation remains valid while it holds'],
            ['Lower Low', 'Market Structure', 'A swing low below the previous meaningful low.', 'bearish structure is progressing if lower highs also hold'],
            ['BOS', 'Market Structure', 'A break of structure in the trend direction.', 'continuation is more likely when the break closes cleanly'],
            ['CHOCH', 'Market Structure', 'A change of character that warns momentum may be shifting.', 'confirmation is needed before calling full reversal'],
            ['Protected High', 'Market Structure', 'A high that should hold if the bearish idea is valid.', 'a break above it can invalidate shorts'],
            ['Protected Low', 'Market Structure', 'A low that should hold if the bullish idea is valid.', 'a break below it can invalidate longs'],
            ['Internal Structure', 'Market Structure', 'Smaller swings inside a larger structure leg.', 'avoid confusing small noise with major direction'],
            ['Major Structure', 'Market Structure', 'Higher timeframe swings defining broader context.', 'avoid entries directly into major opposing levels'],
            ['Support Zone', 'SNR / Key Level', 'An area where buying interest has reacted before.', 'watch candle reaction before buying'],
            ['Resistance Zone', 'SNR / Key Level', 'An area where selling interest has reacted before.', 'watch candle reaction before selling'],
            ['Body and Wick Zone', 'SNR / Key Level', 'A zone using candle bodies and rejection wicks.', 'mark the reaction area rather than one exact line'],
            ['Breakout', 'SNR / Key Level', 'Price closing beyond a meaningful range or level.', 'confirm close quality and avoid wick-only breaks'],
            ['Retest', 'SNR / Key Level', 'Price returning to test a broken level.', 'look for former resistance as support or support as resistance'],
            ['Fakeout', 'SNR / Key Level', 'A failed breakout that returns inside the range.', 'avoid chasing and wait for confirmation'],
            ['Liquidity Sweep', 'SNR / Key Level', 'A stop run through obvious highs/lows before reversal.', 'wait for reclaim before reversal entry'],
            ['Pullback Entry', 'Entry Model', 'Entering after retracement into a planned zone.', 'wait for reaction instead of chasing impulse'],
            ['Breakout Retest Entry', 'Entry Model', 'Entering after breakout and successful retest.', 'avoid entering before breakout is confirmed'],
            ['Momentum Entry', 'Entry Model', 'Entering after strong directional confirmation.', 'manage slippage and avoid late exhaustion'],
            ['Reversal Entry', 'Entry Model', 'Entering after evidence direction may change.', 'require stronger proof than one countertrend candle'],
            ['Confirmation Candle', 'Entry Model', 'A candle that closes in favor of the setup.', 'wait for close if the plan requires confirmation'],
            ['Rejection Wick', 'Entry Model', 'A wick showing price failed to hold beyond a level.', 'read it only at meaningful structure'],
            ['Engulfing Candle', 'Entry Model', 'A candle body that covers the previous body.', 'use it with level and structure context'],
            ['No-Trade Condition', 'Entry Model', 'A situation where rules say to stand aside.', 'protect capital when evidence is unclear'],
            ['SL Placement', 'Risk Management', 'Stop placement beyond setup invalidation.', 'place it where the idea is proven wrong'],
            ['TP Placement', 'Risk Management', 'Target placement near liquidity or structure.', 'avoid unrealistic targets beyond strong opposing zones'],
            ['Breakeven Movement', 'Risk Management', 'Moving stop near entry after price earns protection.', 'avoid moving too early without structure'],
            ['Layering', 'Risk Management', 'Adding positions in stages by rule.', 'calculate combined risk before adding'],
            ['Scaling Out', 'Risk Management', 'Closing partial position at planned areas.', 'reduce exposure without abandoning the setup'],
            ['Risk-to-Reward', 'Risk Management', 'Reward potential compared with risk.', 'skip setups with poor RR even if direction looks right'],
            ['EMA', 'Indicators and Tools', 'A moving average that weights recent price more strongly.', 'use slope and location as context'],
            ['RSI', 'Indicators and Tools', 'A momentum oscillator for overbought/oversold and divergence clues.', 'do not use it as a standalone entry'],
            ['Trendline', 'Indicators and Tools', 'A line connecting meaningful swing highs or lows.', 'combine it with structure and levels'],
            ['VWAP', 'Indicators and Tools', 'Volume-weighted average price used as intraday value.', 'read it with session context'],
            ['Volume Profile', 'Indicators and Tools', 'Volume by price area.', 'identify value areas and low-volume rejection'],
            ['Opening Range', 'Indicators and Tools', 'The high/low of an early session window.', 'watch breakout and fakeout behavior'],
            ['FVG', 'Indicators and Tools', 'A fair value gap left by displacement.', 'use it only when aligned with structure'],
            ['Order Block', 'Indicators and Tools', 'A prior candle zone associated with displacement.', 'avoid using it without confirmation'],
            ['Mitigated Order Block', 'Indicators and Tools', 'An order block already revisited by price.', 'reduce confidence after repeated mitigation'],
            ['POI', 'Indicators and Tools', 'Point of interest where reaction is expected.', 'define why the area matters before entry'],
            ['Gold Volatility', 'Gold / XAUUSD', 'Gold often moves faster and wider than many FX pairs.', 'adapt stop distance and lot size'],
            ['Gold Spread Impact', 'Gold / XAUUSD', 'XAUUSD spread can affect tight entries.', 'include spread in stop and entry planning'],
            ['Gold News Behavior', 'Gold / XAUUSD', 'Gold can spike sharply around USD news.', 'avoid oversized trades during high-impact events'],
            ['H4 Bias', 'Multi-Timeframe', 'Higher timeframe directional context.', 'do not fight HTF without reversal evidence'],
            ['H1 Internal Structure', 'Multi-Timeframe', 'Medium timeframe swing context.', 'map pullback and internal trend'],
            ['M15 Entry Structure', 'Multi-Timeframe', 'Lower timeframe structure for timing.', 'use it for trigger, not overall bias alone'],
            ['Timeframe Conflict', 'Multi-Timeframe', 'Higher and lower timeframes disagree.', 'stand aside when confluence is weak'],
            ['HTF Direction vs LTF Pullback', 'Multi-Timeframe', 'Lower timeframe moves against higher timeframe trend.', 'treat it as pullback until major structure breaks'],
            ['News Filter', 'Risk Filters', 'Avoiding setups around scheduled high-impact news.', 'protect execution and prop-firm limits'],
        ];

        $questions = [];
        foreach ($topics as $index => [$term, $subcategory, $definition, $application]) {
            $questions[] = $this->q(FinalExamQuestion::CATEGORY_TECHNIQUE, $subcategory, FinalExamQuestion::TYPE_TEXT_MCQ, 'Technique definition: what does '.$term.' mean?', $definition, [
                'A guarantee that the next candle will reverse.',
                'A broker setting that removes execution risk.',
                'A reason to trade without invalidation.',
            ], $term.' must be read with context, not as an isolated signal.', $this->difficultyByIndex($index));
            $questions[] = $this->q(FinalExamQuestion::CATEGORY_TECHNIQUE, $subcategory, FinalExamQuestion::TYPE_TEXT_MCQ, 'Technique application: how should a trader apply '.$term.'?', 'They '.$application.'.', [
                'They enter immediately with maximum lot size.',
                'They ignore the higher timeframe and trade the first candle.',
                'They move the stop farther whenever price hesitates.',
            ], 'Professional technical analysis connects the concept to entry, invalidation, and risk.', $this->difficultyByIndex($index + 1));
            $questions[] = $this->q(FinalExamQuestion::CATEGORY_TECHNIQUE, $subcategory, FinalExamQuestion::TYPE_TEXT_MCQ, 'Technique invalidation: what should confirm that a '.$term.' idea is wrong?', 'The price action or level that invalidates the original setup logic.', [
                'The trader feeling uncomfortable.',
                'The profit target being far away.',
                'The previous trade outcome.',
            ], 'Invalidation must come from the setup idea, not emotion or desired profit.', $this->difficultyByIndex($index + 2));
        }

        return $this->takeExact($questions, 150, FinalExamQuestion::CATEGORY_TECHNIQUE);
    }

    private function techniqueChartQuestions(): array
    {
        $templates = $this->techniqueChartTemplates();
        $questions = [];

        for ($i = 0; $i < 100; $i++) {
            $template = $templates[$i % count($templates)];
            $case = $i + 1;
            $questions[] = $this->q(
                FinalExamQuestion::CATEGORY_TECHNIQUE,
                $template['subcategory'],
                FinalExamQuestion::TYPE_CHART_MCQ,
                'Technique chart case '.$case.': based on the chart, what is the most accurate technical read for '.$template['label'].'?',
                $template['correct'],
                $template['wrong'],
                $template['explanation'],
                $this->difficultyByIndex($i + 1),
                null,
                $this->chartDataForTemplate($template, $case, 'technique'),
                $template['key'],
                $template['timeframe']
            );
        }

        return $questions;
    }

    private function psychologyChartScenarioQuestions(): array
    {
        $templates = $this->psychologyChartTemplates();
        $questions = [];

        for ($i = 0; $i < 100; $i++) {
            $template = $templates[$i % count($templates)];
            $case = $i + 1;
            $questions[] = $this->q(
                FinalExamQuestion::CATEGORY_PSYCHOLOGY,
                $template['subcategory'],
                FinalExamQuestion::TYPE_CHART_SCENARIO_MCQ,
                'Psychology chart case '.$case.': after seeing this chart, what is the best trader behavior?',
                $template['correct'],
                $template['wrong'],
                $template['explanation'],
                $this->difficultyByIndex($i),
                $template['scenario'],
                $this->chartDataForTemplate($template, $case, 'psychology'),
                $template['key'],
                $template['timeframe']
            );
        }

        return $questions;
    }

    private function techniqueChartTemplates(): array
    {
        return [
            $this->tech('bullish_bos', 'Bullish BOS', 'Market Structure', 'M15', 'A bullish break of structure is shown.', 'Price breaks and closes above the prior swing high, confirming bullish structure continuation.', 'bullish'),
            $this->tech('bearish_bos', 'Bearish BOS', 'Market Structure', 'M15', 'A bearish break of structure is shown.', 'Price breaks and closes below the prior swing low, confirming bearish structure continuation.', 'bearish'),
            $this->tech('bullish_choch', 'Bullish CHOCH', 'Market Structure', 'M15', 'A bullish change of character is shown.', 'Price was bearish, then breaks the previous lower high, warning that bearish control has changed.', 'bullish'),
            $this->tech('bearish_choch', 'Bearish CHOCH', 'Market Structure', 'M15', 'A bearish change of character is shown.', 'Price was bullish, then breaks the previous higher low, warning that bullish control has changed.', 'bearish'),
            $this->tech('hh_hl_continuation', 'Higher high and higher low continuation', 'Market Structure', 'H1', 'Bullish continuation is valid while the higher low holds.', 'The chart shows higher highs and higher lows with price reacting from the latest higher low.', 'bullish'),
            $this->tech('ll_lh_continuation', 'Lower low and lower high continuation', 'Market Structure', 'H1', 'Bearish continuation is valid while the lower high holds.', 'The chart shows lower lows and lower highs with price rejecting the latest lower high.', 'bearish'),
            $this->tech('support_rejection', 'Support rejection', 'SNR / Key Level', 'M15', 'Support rejection is visible.', 'Price tests the support zone and closes back above it with rejection wicks.', 'bullish'),
            $this->tech('resistance_rejection', 'Resistance rejection', 'SNR / Key Level', 'M15', 'Resistance rejection is visible.', 'Price tests resistance and closes back below it with rejection wicks.', 'bearish'),
            $this->tech('breakout_retest_buy', 'Breakout retest buy', 'Entry Model', 'M15', 'A bullish breakout retest entry is shown.', 'Price closes above resistance, retests it as support, then reacts upward.', 'bullish'),
            $this->tech('breakout_retest_sell', 'Breakout retest sell', 'Entry Model', 'M15', 'A bearish breakout retest entry is shown.', 'Price closes below support, retests it as resistance, then reacts downward.', 'bearish'),
            $this->tech('fake_breakout_above_resistance', 'Fake breakout above resistance', 'SNR / Key Level', 'M5', 'A fake breakout above resistance is shown.', 'Price wicks above resistance but closes back below, showing failure to accept higher.', 'range'),
            $this->tech('fake_breakdown_below_support', 'Fake breakdown below support', 'SNR / Key Level', 'M5', 'A fake breakdown below support is shown.', 'Price wicks below support but closes back above, showing failure to accept lower.', 'range'),
            $this->tech('liquidity_sweep_buy', 'Liquidity sweep buy setup', 'SNR / Key Level', 'M15', 'A buy-side reversal after sell-side liquidity sweep is shown.', 'Price sweeps below the prior low and reclaims support, creating a possible long setup.', 'bullish'),
            $this->tech('liquidity_sweep_sell', 'Liquidity sweep sell setup', 'SNR / Key Level', 'M15', 'A sell-side reversal after buy-side liquidity sweep is shown.', 'Price sweeps above the prior high and falls back below resistance, creating a possible short setup.', 'bearish'),
            $this->tech('trendline_rejection_buy', 'Trendline rejection buy', 'Indicators and Tools', 'H1', 'Bullish trendline rejection is shown.', 'Price respects the rising trendline and rejects from it in line with bullish structure.', 'bullish'),
            $this->tech('trendline_rejection_sell', 'Trendline rejection sell', 'Indicators and Tools', 'H1', 'Bearish trendline rejection is shown.', 'Price respects the falling trendline and rejects from it in line with bearish structure.', 'bearish'),
            $this->tech('ema_bullish_continuation', 'EMA bullish continuation', 'Indicators and Tools', 'M15', 'EMA bullish continuation is shown.', 'Price pulls back to a rising EMA and reacts upward, supporting trend continuation.', 'bullish'),
            $this->tech('ema_bearish_continuation', 'EMA bearish continuation', 'Indicators and Tools', 'M15', 'EMA bearish continuation is shown.', 'Price pulls back to a falling EMA and rejects lower, supporting bearish continuation.', 'bearish'),
            $this->tech('rsi_overbought_reversal', 'RSI overbought reversal', 'Indicators and Tools', 'M15', 'An overbought reversal warning is shown.', 'Price rejects resistance while RSI is overbought, so confirmation is needed before shorts.', 'bearish'),
            $this->tech('rsi_oversold_reversal', 'RSI oversold reversal', 'Indicators and Tools', 'M15', 'An oversold reversal warning is shown.', 'Price rejects support while RSI is oversold, so confirmation is needed before longs.', 'bullish'),
            $this->tech('opening_range_breakout', 'Opening range breakout', 'Session Analysis', 'M5', 'A valid opening range breakout is shown.', 'Price closes outside the opening range and holds above the breakout area.', 'bullish'),
            $this->tech('opening_range_fakeout', 'Opening range fakeout', 'Session Analysis', 'M5', 'An opening range fakeout is shown.', 'Price breaks the range but quickly returns inside, warning against chasing.', 'range'),
            $this->tech('fvg_continuation', 'FVG continuation', 'Indicators and Tools', 'M15', 'A fair value gap continuation setup is shown.', 'Price returns into the imbalance and continues in the original displacement direction.', 'bullish'),
            $this->tech('order_block_reaction', 'Order block reaction', 'Indicators and Tools', 'H1', 'An order block reaction is shown.', 'Price taps the marked order block and reacts in the expected direction.', 'bullish'),
            $this->tech('mitigated_order_block_invalidation', 'Mitigated order block invalidation', 'Indicators and Tools', 'H1', 'The order block is weakened or invalidated.', 'Price has already mitigated the zone and then closes through it, reducing validity.', 'bearish'),
            $this->tech('poi_reaction', 'POI reaction', 'Indicators and Tools', 'M15', 'A reaction from a point of interest is shown.', 'The marked POI aligns with structure and produces a confirmation candle.', 'bullish'),
            $this->tech('htf_bullish_ltf_pullback', 'HTF bullish but LTF pullback', 'Multi-Timeframe', 'M15', 'The lower timeframe move is a pullback inside HTF bullish bias.', 'The higher timeframe remains bullish while lower timeframe retraces into support.', 'bullish'),
            $this->tech('htf_bearish_ltf_pullback', 'HTF bearish but LTF pullback', 'Multi-Timeframe', 'M15', 'The lower timeframe move is a pullback inside HTF bearish bias.', 'The higher timeframe remains bearish while lower timeframe retraces into resistance.', 'bearish'),
            $this->tech('timeframe_conflict_no_trade', 'Timeframe conflict no-trade', 'Multi-Timeframe', 'M15', 'No trade is preferred because timeframe signals conflict.', 'Higher and lower timeframes disagree, so confluence is too weak.', 'range'),
            $this->tech('unclear_structure_no_trade', 'Unclear structure no-trade', 'Market Structure', 'M15', 'No trade is preferred because structure is unclear.', 'The chart is choppy without clean swing sequence or invalidation.', 'range'),
            $this->tech('poor_rr_setup', 'Poor risk-to-reward setup', 'Risk Management', 'M15', 'The setup is invalid because reward-to-risk is poor.', 'The target is too close to justify the stop distance.', 'range'),
            $this->tech('valid_sl_below_protected_low', 'Valid SL below protected low', 'Risk Management', 'M15', 'The valid long stop is below the protected low.', 'A bullish setup is invalid only if price breaks the protected low.', 'bullish'),
            $this->tech('valid_sl_above_protected_high', 'Valid SL above protected high', 'Risk Management', 'M15', 'The valid short stop is above the protected high.', 'A bearish setup is invalid only if price breaks the protected high.', 'bearish'),
            $this->tech('tp_next_liquidity', 'TP at next liquidity area', 'Risk Management', 'M15', 'The logical TP is the next liquidity area.', 'The next swing high/low provides a realistic target before major opposition.', 'bullish'),
            $this->tech('be_after_1r', 'BE movement after price reaches 1R', 'Risk Management', 'M15', 'Moving to breakeven is reasonable after price reaches 1R and structure supports it.', 'The chart shows price has moved enough to reduce risk according to plan.', 'bullish'),
            $this->tech('layer_after_breakout_retest', 'Layering after breakout retest', 'Risk Management', 'M15', 'A controlled layer after breakout retest is valid.', 'The layer is added after confirmation while total risk remains planned.', 'bullish'),
            $this->tech('invalid_layer_too_extended', 'Invalid layer because price is too extended', 'Risk Management', 'M5', 'The layer is invalid because price is extended far from structure.', 'Adding late creates poor RR and emotional exposure.', 'bullish'),
            $this->tech('gold_high_volatility_candle', 'Gold high-volatility candle', 'Gold / XAUUSD', 'M5', 'Gold volatility risk is high.', 'The large XAUUSD candle requires wider risk planning or no trade.', 'range'),
            $this->tech('news_spike_invalid_setup', 'News spike invalid setup', 'Risk Filters', 'M5', 'The setup is invalid because the move is a news spike.', 'The candle is disorderly and news-driven, so technical entry quality is poor.', 'range'),
            $this->tech('session_based_breakout', 'Session-based breakout', 'Session Analysis', 'M15', 'A session breakout is shown.', 'Price breaks the session range with acceptance and follow-through.', 'bullish'),
        ];
    }

    private function psychologyChartTemplates(): array
    {
        return [
            $this->psy('sl_hit_then_tp', 'SL hit then price goes to TP', 'Revenge Trading', 'The trader was stopped out, then price later reached the original TP area. The trader feels angry and wants to re-enter with double lot.', 'Wait for a fresh valid setup and keep the same risk plan.', 'The trade was already invalidated by SL. Re-entering larger is revenge trading.', 'bullish'),
            $this->psy('missed_entry_fomo', 'Missed entry and FOMO candle', 'FOMO', 'Price broke out and ran without retesting. The trader missed the planned entry and fears missing profit.', 'Wait for a retest or another valid setup.', 'Chasing creates poor RR and emotional execution.', 'bullish'),
            $this->psy('floating_profit_close_early', 'Floating profit but trader wants to close too early', 'Cutting Profit Early', 'The trade is in profit but has not reached the planned management area. The trader wants to close because of fear.', 'Follow the preset management plan or planned partial rule.', 'Fear-based exits damage consistency even when they feel safe.', 'bullish'),
            $this->psy('floating_loss_not_invalidated', 'Floating loss but setup not invalidated', 'Fear After Loss', 'Price pulls back against the trade but has not broken invalidation. The trader wants to panic close.', 'Hold only if risk and invalidation remain inside the plan.', 'A trade can fluctuate before invalidation; decisions should follow the plan.', 'range'),
            $this->psy('remove_sl_near_stop', 'Price near SL and trader wants to remove SL', 'Accepting SL', 'Price is close to SL. The trader wants to remove the stop to avoid being wrong.', 'Keep the SL and accept the planned risk.', 'Removing SL turns a controlled loss into uncontrolled risk.', 'bearish'),
            $this->psy('move_tp_due_greed', 'Price near TP and trader wants to move TP further', 'Greed', 'Price is near TP. The trader feels the move is strong and wants to move TP much further.', 'Follow the preset TP or planned trailing rule.', 'Moving TP from greed can turn a good trade into a missed exit.', 'bullish'),
            $this->psy('daily_loss_limit_reached', 'Daily loss limit reached but setup appears', 'Daily Loss Limit Discipline', 'The trader has reached daily loss limit. A clean-looking setup appears later.', 'Stop trading because the daily loss limit has already been reached.', 'Prop-firm rules override one attractive setup.', 'range'),
            $this->psy('winning_streak_weak_setup', 'Winning streak then weak setup appears', 'Overconfidence', 'After three wins, the trader sees a weak setup and feels unusually confident.', 'Skip the weak setup and keep normal criteria.', 'Winning streaks can create overconfidence and lower standards.', 'range'),
            $this->psy('losing_streak_fast_candle', 'Losing streak then fast candle appears', 'Revenge Trading', 'After two losses, a fast candle appears and the trader wants to recover quickly.', 'Pause and only trade a valid planned setup later.', 'Fast recovery urges are revenge trading risk.', 'bearish'),
            $this->psy('all_in_after_loss', 'Trader wants to all-in after loss', 'All-In Behavior', 'After a losing trade, the trader wants to risk heavily on the next setup.', 'Keep risk capped or stop trading to reset.', 'All-in behavior can destroy the account faster than analysis can help.', 'bearish'),
            $this->psy('double_lot_after_win', 'Trader wants to double lot after win', 'Greed After Profit', 'After a strong winner, the trader wants to double lot on the next signal.', 'Keep the same risk plan unless the strategy rules say otherwise.', 'A win does not justify emotional position sizing.', 'bullish'),
            $this->psy('enter_before_confirmation', 'Trader enters before confirmation candle closes', 'Impulse Entry', 'A confirmation candle is still forming. The trader wants to enter before it closes.', 'Wait for the candle to close if the plan requires confirmation.', 'Entering early means the signal can disappear before close.', 'bullish'),
            $this->psy('chase_breakout_no_retest', 'Trader chases breakout without retest', 'FOMO', 'Price breaks out strongly without a retest. The trader wants to chase immediately.', 'Wait for retest or another valid entry model.', 'Chasing late usually worsens RR and discipline.', 'bullish'),
            $this->psy('manual_close_without_plan', 'Trader closes manually without plan', 'Not Following Plan', 'The trade is still valid, but the trader wants to manually close from discomfort.', 'Only close manually if the trade reason is invalidated or rules allow it.', 'Manual exits without rules create inconsistent results.', 'range'),
            $this->psy('move_sl_wider', 'Trader moves SL wider emotionally', 'Moving SL Emotionally', 'Price approaches SL and the trader wants to move it wider.', 'Do not widen SL emotionally; accept the planned invalidation.', 'Widening SL increases risk after entry.', 'bearish'),
            $this->psy('cancel_tp_greed', 'Trader cancels TP due to greed', 'Greed', 'Price is almost at TP and the trader wants to cancel TP because the candle looks strong.', 'Respect TP or use only the planned trailing rule.', 'Greed can erase a high-quality planned exit.', 'bullish'),
            $this->psy('late_layers', 'Trader keeps adding layers too late', 'Overtrading', 'Price is far from the original entry and the trader wants to add more layers.', 'Do not add late layers if risk-to-reward and combined risk are invalid.', 'Late layers are often emotional and expose the account.', 'bullish'),
            $this->psy('unclear_structure_wants_action', 'Trader sees unclear structure but wants action', 'Boredom Trading', 'The market is choppy, but the trader wants action because nothing happened today.', 'Accept the no-trade condition.', 'No-trade discipline prevents low-quality emotional entries.', 'range'),
            $this->psy('trade_high_impact_news', 'Trader trades during high-impact news', 'News Discipline', 'High-impact news is minutes away and the trader feels excited by volatility.', 'Avoid trading if the plan excludes news risk.', 'Excitement around news can lead to slippage and rule breaches.', 'range'),
            $this->psy('daily_target_reached', 'Trader reaches daily target but wants more', 'Knowing When To Stop', 'The daily target is reached, but the trader wants one more trade.', 'Stop or switch to review mode according to the plan.', 'Protecting a good day is part of consistency.', 'bullish'),
            $this->psy('missed_tp_panics', 'Trader missed TP by small distance and panics', 'Emotional Control', 'Price nearly hit TP, pulled back, and the trader panics.', 'Follow the management plan instead of reacting to frustration.', 'Near-misses are normal; panic changes break consistency.', 'range'),
            $this->psy('bored_trade', 'Trader takes trade because bored', 'Patience', 'The chart has no clean setup, but the trader is bored and wants to enter.', 'Do not trade until a valid setup appears.', 'Boredom is not an entry signal.', 'range'),
            $this->psy('follow_signal_no_validation', 'Trader follows signal without validation', 'Following Others', 'Someone posts a signal, but the trader has not validated the chart.', 'Validate the setup with the trader\'s own plan or skip it.', 'Blindly following signals avoids responsibility and process.', 'range'),
            $this->psy('reenter_after_manual_close', 'Trader re-enters immediately after manual close', 'Impulse Control', 'The trader manually closed, then immediately wants to re-enter from regret.', 'Wait for a fresh valid setup.', 'Instant re-entry from regret is emotional trading.', 'bullish'),
            $this->psy('refuse_no_trade_day', 'Trader refuses no-trade day', 'Patience', 'No clean setups appear all day, but the trader feels a day without trades is wasted.', 'Accept the no-trade day.', 'Protecting capital is productive when the market gives no edge.', 'range'),
            $this->psy('ignore_journal_tags', 'Trader ignores journal mistake tags', 'Trade Journaling', 'The journal shows repeated FOMO tags, but the trader wants to ignore them.', 'Review the repeated mistake and adjust the routine.', 'Journal tags are feedback for behavior change.', 'range'),
            $this->psy('break_risk_after_two_losses', 'Trader breaks risk plan after two losses', 'Risk Discipline', 'After two losses, the trader wants to increase lot size to recover.', 'Keep risk fixed or stop trading for review.', 'Increasing risk after losses is a common account-damaging behavior.', 'bearish'),
            $this->psy('overconfident_after_three_wins', 'Trader becomes overconfident after three wins', 'Overconfidence', 'After three wins, the trader believes the next setup cannot fail.', 'Keep normal risk and require full setup confirmation.', 'Overconfidence lowers discipline after success.', 'bullish'),
            $this->psy('hold_overnight_against_plan', 'Trader holds overnight against plan', 'Following Trading Plan', 'The trade has not hit TP, and the trader wants to hold overnight although the plan forbids it.', 'Close or manage according to the written plan.', 'Holding against plan adds unmanaged risk.', 'range'),
            $this->psy('prop_max_loss_warning', 'Trader ignores prop firm max loss warning', 'Prop Firm Rule Discipline', 'The account is near max loss. The trader sees a setup and wants to take it anyway.', 'Stop trading and protect the account from rule breach.', 'Prop-firm survival matters more than one setup.', 'bearish'),
        ];
    }

    private function tech(string $key, string $label, string $subcategory, string $timeframe, string $correct, string $explanation, string $direction): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'subcategory' => $subcategory,
            'timeframe' => $timeframe,
            'correct' => $correct,
            'explanation' => $explanation,
            'direction' => $direction,
            'wrong' => [
                'No valid information can be taken from the chart.',
                'The trader should ignore invalidation and enter immediately.',
                'The opposite signal is confirmed without waiting for structure.',
            ],
        ];
    }

    private function psy(string $key, string $label, string $subcategory, string $scenario, string $correct, string $explanation, string $direction): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'subcategory' => $subcategory,
            'scenario' => $scenario,
            'timeframe' => str_contains($key, 'news') || str_contains($key, 'fomo') ? 'M5' : 'M15',
            'correct' => $correct,
            'explanation' => $explanation,
            'direction' => $direction,
            'wrong' => [
                'Enter immediately with larger lot to maximize or recover quickly.',
                'Remove or move the stop loss because the trader feels strongly.',
                'Ignore the trading plan and decide based on the last candle only.',
            ],
        ];
    }

    private function chartDataForTemplate(array $template, int $case, string $mode): array
    {
        $direction = $template['direction'] ?? 'range';
        $base = 1900 + (($case % 17) * 3);
        $candles = $this->candles($base, $direction, $case);
        $prices = collect($candles)->flatMap(fn ($c) => [$c['high'], $c['low']]);
        $low = (float) $prices->min();
        $high = (float) $prices->max();
        $mid = round(($low + $high) / 2, 2);

        return [
            'symbol' => 'XAUUSD',
            'timeframe' => $template['timeframe'],
            'template_key' => $template['key'],
            'template_label' => $template['label'],
            'mode' => $mode,
            'candles' => $candles,
            'annotations' => [
                ['type' => 'zone', 'label' => str_contains($template['key'], 'resistance') || $direction === 'bearish' ? 'Resistance / protected high' : 'Support / protected low', 'from' => round($mid - 1.2, 2), 'to' => round($mid + 1.2, 2), 'tone' => $direction === 'bearish' ? 'danger' : 'success'],
                ['type' => 'line', 'label' => $mode === 'psychology' ? 'Emotional trigger area' : 'Key decision level', 'price' => $mid, 'tone' => 'primary'],
                ['type' => 'marker', 'label' => $template['label'], 'index' => max(5, count($candles) - 4), 'price' => $direction === 'bearish' ? round($high - 1, 2) : round($low + 1, 2), 'tone' => $direction === 'bearish' ? 'danger' : 'success'],
                ['type' => 'line', 'label' => 'Planned SL / invalidation', 'price' => $direction === 'bearish' ? round($high + 1.5, 2) : round($low - 1.5, 2), 'tone' => 'danger'],
                ['type' => 'line', 'label' => 'Planned TP / liquidity', 'price' => $direction === 'bearish' ? round($low + 0.8, 2) : round($high - 0.8, 2), 'tone' => 'success'],
            ],
            'validation_notes' => $template['explanation'],
        ];
    }

    private function candles(float $base, string $direction, int $seed): array
    {
        $candles = [];
        $close = $base;
        for ($i = 0; $i < 22; $i++) {
            $wave = (($i + $seed) % 4) - 1.5;
            $drift = match ($direction) {
                'bullish' => $i < 8 ? -0.45 : 0.85,
                'bearish' => $i < 8 ? 0.45 : -0.85,
                default => ($i % 2 === 0 ? 0.55 : -0.50),
            };
            if ($direction === 'range' && in_array($i, [12, 13], true)) {
                $drift *= -2.2;
            }
            $open = $close;
            $close = round($close + $drift + ($wave * 0.12), 2);
            $high = round(max($open, $close) + 0.65 + (($i + $seed) % 3) * 0.15, 2);
            $low = round(min($open, $close) - 0.65 - (($i + $seed) % 2) * 0.15, 2);
            $candles[] = [
                'time' => sprintf('%02d:%02d', 8 + intdiv($i, 4), ($i % 4) * 15),
                'open' => round($open, 2),
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume' => 100 + (($i + $seed) % 8) * 18,
            ];
        }

        return $candles;
    }

    private function q(string $category, string $subcategory, string $questionType, string $question, string $correct, array $wrong, string $explanation, string $difficulty, ?string $scenarioText = null, ?array $chartData = null, ?string $chartTemplateKey = null, ?string $chartTimeframe = null): array
    {
        $wrong = collect($wrong)
            ->map(fn ($option): string => trim((string) $option))
            ->reject(fn ($option): bool => $option === '' || $option === $correct)
            ->unique()
            ->values();

        foreach ([
            'Enter without a stop because the setup looks strong.',
            'Ignore the context and decide only after the trade is open.',
            'Increase risk because this situation feels high probability.',
        ] as $fallback) {
            if ($wrong->count() >= 3) {
                break;
            }
            if (! $wrong->contains($fallback) && $fallback !== $correct) {
                $wrong->push($fallback);
            }
        }

        $slot = abs((int) crc32($question)) % 4;
        $options = $wrong->take(3)->values()->all();
        array_splice($options, $slot, 0, [$correct]);

        return [
            'category' => $category,
            'subcategory' => $subcategory,
            'question_type' => $questionType,
            'question' => $question,
            'scenario_text' => $scenarioText,
            'chart_image_path' => null,
            'chart_data_json' => $chartData,
            'chart_template_key' => $chartTemplateKey,
            'chart_timeframe' => $chartTimeframe,
            'option_a' => $options[0],
            'option_b' => $options[1],
            'option_c' => $options[2],
            'option_d' => $options[3],
            'correct_answer' => chr(65 + $slot),
            'explanation' => $explanation,
            'difficulty' => $difficulty,
            'status' => FinalExamQuestion::STATUS_ACTIVE,
        ];
    }

    private function takeExact(array $questions, int $count, string $category): array
    {
        if (count($questions) < $count) {
            throw new RuntimeException($category.' generator produced only '.count($questions).' questions.');
        }

        return array_slice(array_values($questions), 0, $count);
    }

    private function assertValidBank(array $questions): void
    {
        if (count($questions) !== 500) {
            throw new RuntimeException('FinalExamQuestionBankFactory must produce exactly 500 questions.');
        }

        $collection = collect($questions);
        if ($collection->pluck('question')->duplicates()->isNotEmpty()) {
            throw new RuntimeException('Duplicate final exam question text found.');
        }

        $expectedCategories = [
            FinalExamQuestion::CATEGORY_FOUNDATION => 150,
            FinalExamQuestion::CATEGORY_TECHNIQUE => 250,
            FinalExamQuestion::CATEGORY_PSYCHOLOGY => 100,
        ];
        foreach ($expectedCategories as $category => $count) {
            if ($collection->where('category', $category)->count() !== $count) {
                throw new RuntimeException($category.' must contain exactly '.$count.' questions.');
            }
        }

        if ($collection->where('category', FinalExamQuestion::CATEGORY_TECHNIQUE)->where('question_type', FinalExamQuestion::TYPE_TEXT_MCQ)->count() !== 150) {
            throw new RuntimeException('Technique text MCQ count must be 150.');
        }
        if ($collection->where('category', FinalExamQuestion::CATEGORY_TECHNIQUE)->where('question_type', FinalExamQuestion::TYPE_CHART_MCQ)->count() !== 100) {
            throw new RuntimeException('Technique chart MCQ count must be 100.');
        }
        if ($collection->where('category', FinalExamQuestion::CATEGORY_PSYCHOLOGY)->where('question_type', FinalExamQuestion::TYPE_CHART_SCENARIO_MCQ)->count() !== 100) {
            throw new RuntimeException('Psychology chart scenario MCQ count must be 100.');
        }

        foreach ($questions as $question) {
            if (! in_array($question['correct_answer'], ['A', 'B', 'C', 'D'], true) || trim((string) $question['explanation']) === '') {
                throw new RuntimeException('Every final exam question needs one answer key and explanation.');
            }
            if (in_array($question['question_type'], [FinalExamQuestion::TYPE_CHART_MCQ, FinalExamQuestion::TYPE_CHART_SCENARIO_MCQ], true)
                && empty($question['chart_data_json']) && empty($question['chart_image_path'])) {
                throw new RuntimeException('Chart question is missing chart data.');
            }
        }
    }

    private function difficultyByIndex(int $index): string
    {
        return [
            FinalExamQuestion::DIFFICULTY_BEGINNER,
            FinalExamQuestion::DIFFICULTY_INTERMEDIATE,
            FinalExamQuestion::DIFFICULTY_ADVANCED,
            FinalExamQuestion::DIFFICULTY_INTERMEDIATE,
        ][$index % 4];
    }
}
