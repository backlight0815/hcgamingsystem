<?php

namespace Database\Seeders;

use App\Models\TradingExamOption;
use App\Models\TradingExamQuestion;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class TradingExamQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::whereIn('role_id', [1, 2])->orderBy('id')->value('id') ?: User::orderBy('id')->value('id');

        if (! $adminId) {
            throw new RuntimeException('At least one user is required before seeding trading exam questions.');
        }

        $questions = collect()
            ->merge($this->conceptQuestions())
            ->merge($this->riskRewardQuestions())
            ->merge($this->positionSizingQuestions())
            ->merge($this->marginQuestions())
            ->merge($this->technicalScenarioQuestions())
            ->merge($this->psychologyAndProcessQuestions())
            ->take(200)
            ->values();

        if ($questions->count() < 200) {
            throw new RuntimeException('TradingExamQuestionSeeder must provide at least 200 questions.');
        }

        foreach ($questions as $data) {
            $question = TradingExamQuestion::updateOrCreate(
                ['question_text' => $data['question_text']],
                [
                    'created_by' => $adminId,
                    'reviewed_by' => $adminId,
                    'category' => $data['category'],
                    'difficulty' => $data['difficulty'],
                    'explanation' => $data['explanation'],
                    'status' => TradingExamQuestion::STATUS_APPROVED,
                    'review_note' => 'Seeded professional trading examination question.',
                    'reviewed_at' => now(),
                ]
            );

            $question->options()->delete();

            foreach ($data['options'] as $index => $optionText) {
                TradingExamOption::create([
                    'trading_exam_question_id' => $question->id,
                    'option_text' => $optionText,
                    'is_correct' => $index === $data['correct_option'],
                    'position' => $index + 1,
                ]);
            }
        }
    }

    private function conceptQuestions(): array
    {
        $items = [
            ['Pip', 'The smallest standard price movement commonly quoted for most forex pairs.', ['The broker commission charged per lot.', 'The total account equity after floating trades.', 'A guaranteed profit target.']],
            ['Spread', 'The difference between the bid price and ask price.', ['The distance between support and resistance only.', 'The amount of leverage applied to a trade.', 'The final profit after closing a position.']],
            ['Bid price', 'The price at which you can sell to the market.', ['The price used only for pending buy orders.', 'The price where a stop loss is always placed.', 'The price after commission is deducted.']],
            ['Ask price', 'The price at which you can buy from the market.', ['The price at which all trades close automatically.', 'The price where pending sell orders trigger.', 'The average of all previous candles.']],
            ['Market order', 'An order intended to execute immediately at the best available price.', ['An order that waits for a better price only.', 'An order that guarantees no slippage.', 'An order that closes only at the daily candle close.']],
            ['Limit order', 'An order to buy below current price or sell above current price.', ['An order to enter only after price breaks momentum.', 'An order that ignores the requested entry price.', 'An order that automatically trails profit.']],
            ['Stop order', 'An order that triggers after price reaches a specified stop level.', ['An order that can only reduce position size.', 'An order that removes all market risk.', 'An order used only for deposits and withdrawals.']],
            ['Stop-loss order', 'An exit order used to limit loss if price moves against the trade.', ['An entry order used to increase leverage.', 'A tool that guarantees the exact exit during all market gaps.', 'A signal that confirms trend direction.']],
            ['Take-profit order', 'An exit order used to close a trade at a planned profit target.', ['An order that raises account leverage.', 'An order that prevents all losses.', 'A calculation of overnight swap.']],
            ['Slippage', 'The difference between expected execution price and actual execution price.', ['The difference between gross profit and net profit.', 'The number of losing trades in a row.', 'The distance between two moving averages.']],
            ['Liquidity', 'How easily an asset can be bought or sold without large price impact.', ['How much interest is paid overnight.', 'The number of indicators on a chart.', 'A guarantee that price will reverse.']],
            ['Volatility', 'The size and speed of price movement over a period.', ['The broker password reset process.', 'The fixed value of one pip for every account.', 'The number of open trades allowed by a platform.']],
            ['Leverage', 'Borrowed exposure that lets a trader control a larger position with smaller margin.', ['A way to remove risk from a trade.', 'A guaranteed method to increase win rate.', 'The same thing as a stop loss.']],
            ['Margin', 'Capital set aside by the broker to support an open leveraged position.', ['The maximum profit from a trade.', 'The market close time for a symbol.', 'A pattern formed by two candlesticks.']],
            ['Drawdown', 'A decline from a peak account value to a lower value.', ['The total deposits made into an account.', 'The number of trades placed in a week.', 'The amount of spread paid per trade only.']],
            ['Equity', 'Account balance plus or minus current floating profit and loss.', ['Only the original deposit amount.', 'Only closed trade profit.', 'The maximum leverage offered by the broker.']],
            ['Balance', 'The account value after closed trades, excluding open floating profit or loss.', ['The live value including all floating trades.', 'The required margin only.', 'The number of lots currently open.']],
            ['Reward-to-risk ratio', 'The planned potential reward divided by the planned potential loss.', ['The win rate divided by total trades.', 'The spread divided by commission.', 'The margin divided by leverage.']],
            ['Win rate', 'The percentage of trades that close profitably.', ['The average reward per losing trade.', 'The amount risked on every trade.', 'The speed of order execution.']],
            ['Expectancy', 'The average expected result per trade based on win rate and average win/loss.', ['The maximum number of trades per day.', 'The distance from entry to stop only.', 'The total margin requirement.']],
            ['Lot size', 'The position volume used for a trade.', ['The candle body height only.', 'The minimum account balance required to log in.', 'The number of indicators on the template.']],
            ['Swap', 'An overnight financing credit or charge for holding a position.', ['The difference between entry and stop loss.', 'A guaranteed reversal candlestick.', 'The act of deleting a trade journal.']],
            ['Support', 'A price area where buying interest may slow or reverse a decline.', ['The highest leverage available.', 'A guaranteed buy entry.', 'The commission paid on a losing trade.']],
            ['Resistance', 'A price area where selling interest may slow or reverse an advance.', ['A guaranteed sell signal.', 'The amount of free margin only.', 'A broker login restriction.']],
            ['Trendline', 'A line drawn to connect meaningful swing highs or lows.', ['A mandatory broker order type.', 'A fixed profit target for every trade.', 'A report that lists all swaps.']],
            ['Breakout', 'Price moving beyond a defined support, resistance, or range boundary.', ['Price staying inside a tight range.', 'A candle with no wick.', 'A forced account logout.']],
            ['Pullback', 'A temporary move against the dominant trend.', ['A permanent broker spread increase.', 'A trade with no stop loss.', 'A candle that must always reverse trend.']],
            ['Consolidation', 'A period where price moves sideways within a relatively narrow range.', ['A sudden margin call only.', 'A guaranteed trending market.', 'A platform file export.']],
            ['Higher high and higher low', 'A basic sign of an uptrend structure.', ['A basic sign of a downtrend structure.', 'Proof that the trade cannot lose.', 'A type of pending order.']],
            ['Lower high and lower low', 'A basic sign of a downtrend structure.', ['A basic sign of an uptrend structure.', 'A method for calculating swap.', 'A guaranteed reversal.']],
            ['Moving average', 'An indicator that smooths price by averaging values over a chosen period.', ['A tool that predicts exact future price.', 'A broker fee charged at entry.', 'A fixed lot size calculator.']],
            ['RSI', 'A momentum oscillator often used to assess overbought or oversold conditions.', ['A broker settlement code.', 'A fixed stop loss value.', 'A type of market order.']],
            ['MACD', 'A momentum indicator based on relationships between moving averages.', ['A risk percentage setting in the broker.', 'A guaranteed news filter.', 'A candlestick pattern with no body.']],
            ['ATR', 'An indicator that estimates average true range and market volatility.', ['A tool that shows account password strength.', 'A measure of broker deposit speed.', 'A guaranteed entry signal.']],
            ['Volume', 'The amount of trading activity during a period.', ['The same as account balance.', 'The fixed value of each pip.', 'The exact number of future buyers.']],
            ['Position sizing', 'Choosing trade volume based on risk, stop distance, and account size.', ['Opening the largest trade the platform allows.', 'Moving the stop loss after every candle automatically.', 'Selecting a chart color template.']],
            ['Correlation', 'The tendency of two markets or instruments to move together or oppositely.', ['A guaranteed arbitrage opportunity.', 'A broker withdrawal method.', 'The same as leverage.']],
            ['Divergence', 'A disagreement between price direction and an indicator direction.', ['A guarantee that price will reverse immediately.', 'A fixed rule to avoid stop losses.', 'The spread during news.']],
            ['Candlestick body', 'The area between a candle open and close.', ['The distance between bid and ask.', 'The full account equity curve.', 'The broker server time only.']],
            ['Candlestick wick', 'The line showing price traded beyond the open-close body.', ['The commission charged by the broker.', 'The leverage used by the account.', 'The number of closed positions.']],
            ['Doji candle', 'A candle where open and close are very close, often showing indecision.', ['A candle that guarantees continuation.', 'A mandatory exit signal.', 'A candle that cannot have wicks.']],
            ['Engulfing pattern', 'A two-candle pattern where the second candle body engulfs the prior body.', ['A margin calculation method.', 'A spread rebate program.', 'A fixed entry for all trend trades.']],
            ['Pin bar', 'A candle with a long wick and small body, often showing rejection.', ['A broker account PIN reset.', 'A candle with no high or low.', 'A guaranteed breakout signal.']],
            ['Economic calendar', 'A schedule of market-moving economic releases and events.', ['A list of all broker passwords.', 'A chart type that removes volatility.', 'A fixed position sizing table.']],
            ['Backtesting', 'Testing a trading idea on historical market data.', ['Changing leverage during a live loss.', 'Closing all trades before rollover every day.', 'Deleting losing trades from the journal.']],
            ['Trade journal', 'A structured record of trades, reasons, execution, and lessons.', ['A broker bonus balance.', 'A chart indicator that guarantees profit.', 'A report only for deposits.']],
            ['Demo account', 'A practice account using simulated funds.', ['An account that removes all execution risk in live markets.', 'A permanent proof of profitability.', 'A broker account with no spreads.']],
            ['Overtrading', 'Taking too many trades or low-quality trades outside the plan.', ['Reducing exposure after a large win.', 'Documenting trade reasons carefully.', 'Waiting for a confirmed setup.']],
            ['Revenge trading', 'Entering impulsive trades to recover losses emotionally.', ['Reducing position size after a loss.', 'Following a written trading plan.', 'Stopping after the daily loss limit.']],
            ['Risk per trade', 'The portion of account equity intentionally exposed on one trade idea.', ['The total account balance.', 'The broker spread only.', 'The number of indicators on screen.']],
            ['Trailing stop', 'A stop loss that moves with favorable price movement based on rules.', ['A stop that only increases risk.', 'A take profit with no exit level.', 'A platform login timeout.']],
            ['Breakeven stop', 'A stop adjusted near entry so the trade has little or no remaining loss risk.', ['A guaranteed profit lock on every broker.', 'A way to double the original position.', 'A candle pattern with equal open and close.']],
            ['Partial close', 'Closing part of a position while leaving the rest open.', ['Deleting part of the account history.', 'A method to avoid recording trades.', 'A platform password change.']],
            ['Trading plan', 'A written framework for setups, risk, execution, and review.', ['A guarantee that a strategy cannot lose.', 'A broker deposit agreement only.', 'A list of random trades to try.']],
            ['Risk of ruin', 'The chance that losses reduce capital to a point where trading cannot continue.', ['The guaranteed profit after a winning streak.', 'The number of candles in a session.', 'The broker server location.']],
        ];

        return collect($items)->map(fn ($item) => $this->q(
            $item[0],
            $this->difficultyForConcept($item[0]),
            'In trading, what does "' . $item[0] . '" mean?',
            $item[1],
            $item[2],
            $item[0] . ' is a core trading term. Understanding it helps traders read markets and manage risk more professionally.'
        ))->all();
    }

    private function riskRewardQuestions(): array
    {
        $scenarios = [
            ['EUR/USD long setup', 50, 100], ['GBP/USD short setup', 40, 80], ['XAU/USD reversal setup', 75, 150],
            ['USD/JPY breakout setup', 30, 90], ['AUD/USD pullback setup', 25, 50], ['NASDAQ intraday setup', 60, 120],
            ['BTC/USD swing setup', 500, 1500], ['Oil continuation setup', 45, 90], ['EUR/JPY range setup', 35, 70],
            ['GBP/JPY momentum setup', 80, 160], ['S&P 500 index setup', 20, 60], ['USD/CAD trend setup', 45, 135],
            ['Silver breakout setup', 60, 90], ['DAX index setup', 50, 100], ['NZD/USD support setup', 30, 45],
            ['ETH/USD pullback setup', 250, 500], ['Copper swing setup', 40, 120], ['CHF/JPY rejection setup', 55, 110],
            ['EUR/AUD continuation setup', 65, 130], ['Gold scalp setup', 35, 70], ['US30 reversal setup', 100, 200],
            ['CAD/JPY trend setup', 40, 100], ['GBP/AUD breakout setup', 70, 140], ['USD/CHF range setup', 25, 75],
            ['EUR/GBP structure setup', 20, 40],
        ];

        return collect($scenarios)->map(function ($scenario) {
            [$name, $risk, $reward] = $scenario;
            $correct = $this->ratioLabel($reward / $risk);
            $pool = ['0.5:1', '1:1', '1.5:1', '2:1', '2.5:1', '3:1', '4:1'];
            $incorrect = collect($pool)->reject(fn ($value) => $value === $correct)->take(3)->values()->all();

            return $this->q(
                'Risk / Reward',
                'risk',
                $name . ' risks ' . $risk . ' pips/points to target ' . $reward . ' pips/points. What is the reward-to-risk ratio?',
                $correct,
                $incorrect,
                'Reward-to-risk is reward divided by risk: ' . $reward . ' / ' . $risk . ' = ' . $correct . '.'
            );
        })->all();
    }

    private function positionSizingQuestions(): array
    {
        $scenarios = [
            [10000, 1, 50, 0.10], [5000, 2, 40, 0.10], [2000, 1, 20, 0.10], [15000, 1, 30, 0.10], [10000, 0.5, 25, 0.10],
            [8000, 1, 40, 0.10], [12000, 1.5, 60, 0.10], [3000, 2, 30, 0.10], [25000, 1, 100, 0.10], [6000, 1, 15, 0.10],
            [4000, 0.5, 20, 0.10], [20000, 2, 80, 0.10], [7500, 1, 25, 0.10], [9000, 1, 45, 0.10], [18000, 0.5, 30, 0.10],
            [11000, 1, 55, 0.10], [16000, 1.25, 50, 0.10], [14000, 1, 70, 0.10], [22000, 0.5, 55, 0.10], [1000, 1, 10, 0.10],
            [30000, 1, 75, 0.10], [12500, 0.8, 40, 0.10], [4500, 1, 30, 0.10], [7000, 2, 70, 0.10], [9500, 1, 38, 0.10],
        ];

        return collect($scenarios)->map(function ($scenario) {
            [$balance, $riskPercent, $stopDistance, $riskPerMicroLotPoint] = $scenario;
            $riskAmount = $balance * ($riskPercent / 100);
            $microLots = $riskAmount / ($stopDistance * $riskPerMicroLotPoint);
            $correct = $this->numberLabel($microLots) . ' micro lots';
            $incorrect = [
                $this->numberLabel(max(1, $microLots / 2)) . ' micro lots',
                $this->numberLabel($microLots * 2) . ' micro lots',
                $this->numberLabel($microLots + 10) . ' micro lots',
            ];

            return $this->q(
                'Position Sizing',
                'risk',
                'An account has ' . $this->money($balance) . ', risks ' . $riskPercent . '% per trade, and uses a ' . $stopDistance . '-pip stop. If each micro lot risks ' . $this->money($riskPerMicroLotPoint) . ' per pip, what position size matches the risk plan?',
                $correct,
                $incorrect,
                'Risk amount is ' . $this->money($riskAmount) . '. Position size is risk amount divided by stop distance and pip value: ' . $this->money($riskAmount) . ' / (' . $stopDistance . ' x ' . $this->money($riskPerMicroLotPoint) . ') = ' . $correct . '.'
            );
        })->all();
    }

    private function marginQuestions(): array
    {
        $scenarios = [
            [10000, 20], [15000, 30], [5000, 10], [25000, 50], [12000, 30],
            [30000, 20], [8000, 40], [6000, 30], [20000, 25], [50000, 100],
            [18000, 30], [9000, 15], [40000, 50], [7500, 25], [16000, 20],
            [22000, 50], [11000, 10], [35000, 70], [4500, 30], [28000, 40],
        ];

        return collect($scenarios)->map(function ($scenario) {
            [$notional, $leverage] = $scenario;
            $margin = $notional / $leverage;

            return $this->q(
                'Margin / Leverage',
                'foundation',
                'If a position has notional exposure of ' . $this->money($notional) . ' and leverage is ' . $leverage . ':1, approximately how much margin is required?',
                $this->money($margin),
                [$this->money($margin * 2), $this->money(max(1, $margin / 2)), $this->money($notional)],
                'Required margin is notional exposure divided by leverage: ' . $this->money($notional) . ' / ' . $leverage . ' = ' . $this->money($margin) . '.'
            );
        })->all();
    }

    private function technicalScenarioQuestions(): array
    {
        return [
            $this->q('RSI', 'technical', 'RSI is above 75 after a strong rally. What is the most professional interpretation?', 'Momentum is strong but the market may be overbought, so confirmation is needed before fading it.', ['A sell trade is guaranteed to win.', 'The market must continue upward forever.', 'The spread will become zero.'], 'RSI above 70 often warns of overbought conditions, but it is not a standalone reversal signal.'),
            $this->q('RSI', 'technical', 'RSI is below 25 after a sharp selloff. What should a trader understand?', 'The market may be oversold, but price action confirmation is still needed.', ['A buy trade is guaranteed to win.', 'The trend is always finished.', 'Risk management is no longer needed.'], 'Oversold readings can persist in strong trends, so confirmation and risk control still matter.'),
            $this->q('Moving Averages', 'technical', 'Price is above a rising 200-period moving average. What does this usually suggest?', 'The longer-term bias is bullish unless structure changes.', ['The next candle must close lower.', 'The market has no volatility.', 'A stop loss is unnecessary.'], 'A rising 200-period average often indicates broader bullish bias.'),
            $this->q('Moving Averages', 'technical', 'A 20-period moving average crosses below a 50-period moving average. What does this commonly suggest?', 'Short-term momentum may be weakening.', ['The account balance will double.', 'The broker will close the market.', 'The spread must tighten.'], 'A short average crossing below a longer average can warn of weakening momentum.'),
            $this->q('ATR', 'technical', 'ATR expands sharply compared with prior sessions. What does that indicate?', 'Volatility has increased.', ['Margin requirement is always reduced.', 'The trend direction is guaranteed bullish.', 'All signals are invalid.'], 'ATR measures range/volatility, not direction.'),
            $this->q('ATR', 'technical', 'A trader uses ATR to set stops. Why might that help?', 'It adapts stop distance to current market volatility.', ['It guarantees the stop will never be hit.', 'It removes the need for a target.', 'It predicts the next news result.'], 'Volatility-based stops can better match current market conditions.'),
            $this->q('Support / Resistance', 'technical', 'Price tests a resistance zone three times but fails to close above it. What is the zone showing?', 'Selling pressure or supply may be present near that level.', ['The zone has no trading relevance.', 'The market must gap upward.', 'All buyers are profitable.'], 'Repeated rejection can show supply or profit-taking near resistance.'),
            $this->q('Support / Resistance', 'technical', 'Price breaks above resistance and later retests it as support. What is this commonly called?', 'A breakout and retest.', ['A margin call.', 'A swap rollover.', 'A forced liquidation.'], 'Former resistance can become support after a confirmed breakout.'),
            $this->q('Trend Structure', 'technical', 'A chart forms consecutive higher highs and higher lows. What structure is shown?', 'Uptrend structure.', ['Downtrend structure.', 'Sideways structure only.', 'A guaranteed reversal.'], 'Higher highs and higher lows are basic signs of an uptrend.'),
            $this->q('Trend Structure', 'technical', 'A chart forms consecutive lower highs and lower lows. What structure is shown?', 'Downtrend structure.', ['Uptrend structure.', 'A guaranteed breakout upward.', 'A broker execution error.'], 'Lower highs and lower lows are basic signs of a downtrend.'),
            $this->q('Breakout', 'technical', 'A candle closes strongly above a long-held resistance with above-average volume. What does that support?', 'A potential bullish breakout.', ['A confirmed margin call.', 'A guaranteed false breakout.', 'A lower spread by rule.'], 'A strong close with volume can support breakout quality.'),
            $this->q('False Breakout', 'technical', 'Price breaks above resistance, quickly returns below it, and closes back inside the range. What is this?', 'A possible false breakout.', ['A perfect continuation signal.', 'A swap fee calculation.', 'A mandatory buy signal.'], 'Failure to hold beyond a level can signal a false breakout.'),
            $this->q('Candlesticks', 'technical', 'A bullish engulfing pattern appears at support after a decline. What is the usual interpretation?', 'Buyers may be gaining control, but confirmation is still needed.', ['The trade cannot lose.', 'It means spreads are free.', 'It only matters during weekends.'], 'Bullish engulfing at support can show a shift in pressure.'),
            $this->q('Candlesticks', 'technical', 'A bearish engulfing pattern forms near resistance after a rally. What can it suggest?', 'Sellers may be gaining control near resistance.', ['Price must rise immediately.', 'The broker has rejected the order.', 'The account equity is fixed.'], 'Bearish engulfing near resistance can warn of selling pressure.'),
            $this->q('Candlesticks', 'technical', 'A long upper wick forms after price pushes into resistance. What can it show?', 'Rejection of higher prices.', ['Guaranteed continuation upward.', 'No trading activity occurred.', 'Only buyers were active.'], 'Upper wicks often show price traded higher but could not hold there.'),
            $this->q('Candlesticks', 'technical', 'A doji forms after a strong one-way move. What is the practical meaning?', 'Indecision may be entering the market.', ['A guaranteed trend continuation.', 'A fixed profit target.', 'A broker system shutdown.'], 'Doji candles show open and close are close, often reflecting indecision.'),
            $this->q('Divergence', 'technical', 'Price makes a higher high while RSI makes a lower high. What is this called?', 'Bearish divergence.', ['Bullish divergence.', 'Positive swap.', 'A limit order.'], 'Bearish divergence can warn that upside momentum is weakening.'),
            $this->q('Divergence', 'technical', 'Price makes a lower low while RSI makes a higher low. What is this called?', 'Bullish divergence.', ['Bearish divergence.', 'A market order.', 'A margin increase.'], 'Bullish divergence can warn that downside momentum is weakening.'),
            $this->q('Volume', 'technical', 'A breakout occurs with very low volume compared with recent candles. What is a reasonable concern?', 'The breakout may lack participation and could fail.', ['The breakout is guaranteed strong.', 'All spreads become zero.', 'The stop loss cannot be hit.'], 'Breakouts with weak participation can be less reliable.'),
            $this->q('MACD', 'technical', 'MACD histogram moves from negative toward positive. What does it generally indicate?', 'Bearish momentum may be decreasing and bullish momentum may be building.', ['Margin is automatically reduced.', 'Price is guaranteed to reverse at once.', 'The market is closed.'], 'MACD histogram reflects changes in momentum.'),
            $this->q('Multiple Timeframes', 'technical', 'A trader finds a buy setup on M15 while H4 is in a strong downtrend. What is the main risk?', 'The lower-timeframe setup may be fighting higher-timeframe pressure.', ['The trade has no risk.', 'The H4 chart is irrelevant for every strategy.', 'The spread will always be lower.'], 'Higher-timeframe context can influence lower-timeframe trades.'),
            $this->q('Market Structure', 'technical', 'After an uptrend, price breaks the most recent higher low. What can this warn?', 'Trend structure may be changing.', ['Trend strength is guaranteed increasing.', 'The broker changed leverage.', 'All indicators must be removed.'], 'Breaking a key swing low can suggest a structural shift.'),
            $this->q('Range Trading', 'technical', 'Price repeatedly bounces between support and resistance without directional progress. What condition is likely?', 'A range or consolidation.', ['A confirmed one-way trend.', 'A margin requirement error.', 'A guaranteed breakout.'], 'Sideways movement between boundaries is range behavior.'),
            $this->q('Gap Risk', 'risk', 'Why is holding positions over major weekend or news gaps risky?', 'Price can reopen far from the stop level, causing slippage.', ['Stops become guaranteed at exact price.', 'The market removes all volatility.', 'Every broker cancels losses.'], 'Gaps can skip planned stop levels, creating execution risk.'),
            $this->q('News Trading', 'risk', 'Why might spreads widen around high-impact news releases?', 'Liquidity can thin while volatility rises.', ['The exchange gives free trades.', 'All orders become limit orders.', 'The market becomes risk-free.'], 'High-impact news can reduce liquidity and increase execution costs.'),
            $this->q('Session Awareness', 'platform', 'Why should a forex trader know major session overlaps?', 'Liquidity and volatility often change during session overlaps.', ['It guarantees every trade wins.', 'It removes swap charges.', 'It changes the account password.'], 'London/New York overlap often has different liquidity from quiet sessions.'),
            $this->q('Trend Continuation', 'technical', 'In an uptrend, price pulls back to a rising moving average and forms bullish rejection. What is this often considered?', 'A possible continuation entry area.', ['A guaranteed downtrend.', 'A mandatory account close.', 'A swap calculation.'], 'Pullbacks to dynamic support can create continuation setups.'),
            $this->q('Stop Placement', 'risk', 'Why is placing a stop just beyond a logical invalidation level useful?', 'It exits when the trade idea is likely wrong.', ['It guarantees no loss.', 'It increases spread income.', 'It removes the need for journaling.'], 'Stops should relate to the point where the setup is invalidated.'),
            $this->q('Confluence', 'technical', 'A setup has trend support, horizontal support, and bullish price action. What is this combination called?', 'Confluence.', ['Overleveraging.', 'Rollover.', 'Forced liquidation.'], 'Confluence means multiple independent factors support a trade idea.'),
            $this->q('Chasing Price', 'psychology', 'A trader buys after a large candle far above the planned entry. What is the main issue?', 'They may be chasing and worsening reward-to-risk.', ['They are guaranteed more profit.', 'They have removed execution risk.', 'They have improved the stop automatically.'], 'Late entries often reduce reward-to-risk and increase emotional risk.'),
        ];
    }

    private function psychologyAndProcessQuestions(): array
    {
        return [
            $this->q('Trading Plan', 'psychology', 'A trader takes a setup not listed in the trading plan because it "looks good". What is the main problem?', 'The trader is breaking process discipline.', ['The trader has guaranteed flexibility profit.', 'The broker requires this behavior.', 'It automatically improves expectancy.'], 'Consistent process matters because unplanned trades are difficult to evaluate.'),
            $this->q('Journaling', 'psychology', 'What is the main purpose of recording screenshots and reasons in a trading journal?', 'To identify repeatable strengths and mistakes.', ['To hide losing trades.', 'To increase leverage automatically.', 'To avoid reviewing performance.'], 'A journal converts trading experience into measurable feedback.'),
            $this->q('Daily Loss Limit', 'risk', 'Why can a daily loss limit help traders?', 'It prevents one bad session from becoming a major account drawdown.', ['It guarantees profit the next day.', 'It removes the need for stops.', 'It increases broker leverage.'], 'Loss limits protect capital and reduce emotional decision-making.'),
            $this->q('Overconfidence', 'psychology', 'After several wins, a trader doubles size without a plan. What bias is likely showing?', 'Overconfidence.', ['Patience.', 'Risk neutrality.', 'Platform discipline.'], 'Winning streaks can lead to oversized risk if the trader becomes overconfident.'),
            $this->q('Revenge Trading', 'psychology', 'A trader immediately opens a larger trade after a loss to win it back. What behavior is this?', 'Revenge trading.', ['Systematic scaling.', 'Portfolio rebalancing.', 'Scheduled rollover.'], 'Revenge trading is emotionally driven and usually violates risk controls.'),
            $this->q('FOMO', 'psychology', 'A trader enters late because they are afraid to miss the move. What is this commonly called?', 'Fear of missing out.', ['Position sizing.', 'Hedging.', 'Backtesting.'], 'FOMO can cause late entries and poor reward-to-risk.'),
            $this->q('Patience', 'psychology', 'What should a trader do when price has not reached their planned entry zone?', 'Wait or skip the trade.', ['Enter immediately at any price.', 'Increase lot size to compensate.', 'Remove the stop loss.'], 'Waiting for planned conditions supports consistent execution.'),
            $this->q('Process Review', 'psychology', 'A profitable trade broke every rule in the plan. How should it be reviewed?', 'As poor process despite positive outcome.', ['As perfect because it made money.', 'As irrelevant and unrecorded.', 'As proof rules are useless.'], 'Good outcomes can still come from bad process; traders must review both.'),
            $this->q('Risk Consistency', 'risk', 'Why is risking a similar percentage per trade useful?', 'It keeps losses comparable and protects against emotional sizing.', ['It guarantees a high win rate.', 'It removes all drawdowns.', 'It makes spread disappear.'], 'Consistent risk helps performance data remain meaningful.'),
            $this->q('Sample Size', 'foundation', 'Why is judging a strategy after only three trades unreliable?', 'The sample size is too small to evaluate expectancy.', ['Three trades prove all future results.', 'The strategy has no variance.', 'Backtesting is impossible after three trades.'], 'Trading systems need enough samples to separate edge from randomness.'),
            $this->q('Checklist', 'platform', 'What is the purpose of a pre-trade checklist?', 'To confirm setup, risk, news, and execution conditions before entry.', ['To guarantee a winning trade.', 'To replace risk management.', 'To make every signal valid.'], 'A checklist reduces avoidable errors before execution.'),
            $this->q('News Filter', 'risk', 'Why might a trader avoid opening a trade minutes before high-impact news?', 'Spread and volatility can increase sharply.', ['Markets become completely predictable.', 'Orders become free.', 'Risk becomes zero.'], 'High-impact news can create sudden movement and poor fills.'),
            $this->q('Trade Management', 'risk', 'A trader moves a stop loss farther away after price moves against them. What is the risk?', 'They are increasing loss beyond the original plan.', ['They are reducing risk automatically.', 'They are locking profit.', 'They are improving fill quality.'], 'Moving stops farther away can invalidate the risk plan.'),
            $this->q('Taking Profit', 'risk', 'Why can partial profit-taking be useful?', 'It can reduce exposure while keeping some participation.', ['It guarantees the remaining trade wins.', 'It doubles leverage automatically.', 'It removes all future risk.'], 'Partial closes can balance realized profit and remaining opportunity.'),
            $this->q('Breakeven Stop', 'risk', 'When is moving a stop to breakeven usually most logical?', 'After price has moved enough in favor to justify reducing risk.', ['Immediately after entry every time.', 'Only after a margin call.', 'Before the order is opened.'], 'Breakeven moves should be rule-based and not too early.'),
            $this->q('Drawdown Response', 'psychology', 'What is a professional response to a meaningful drawdown?', 'Reduce risk, review data, and confirm whether rules are being followed.', ['Double size to recover quickly.', 'Stop journaling losing trades.', 'Remove the trading plan.'], 'Drawdowns require risk control and review, not emotional escalation.'),
            $this->q('Strategy Drift', 'psychology', 'A trader keeps adding new indicators after every loss. What might this indicate?', 'Strategy drift and lack of stable testing.', ['Perfect optimization.', 'Guaranteed improvement.', 'Reduced complexity.'], 'Constant changes make it hard to know what actually works.'),
            $this->q('Trading Hours', 'platform', 'Why should a trader know the symbol trading hours?', 'Orders, spreads, and liquidity can change near open/close times.', ['Trading hours guarantee direction.', 'Trading hours remove all fees.', 'Trading hours replace analysis.'], 'Market hours affect execution conditions.'),
            $this->q('Broker Costs', 'platform', 'Why should commission and spread be included in performance review?', 'They reduce net expectancy.', ['They increase gross profit automatically.', 'They are unrelated to scalping.', 'They only matter on winning trades.'], 'Trading costs can turn a small gross edge into a net loss.'),
            $this->q('Risk Reward', 'risk', 'A setup has 20 pips risk and 20 pips reward. What is the reward-to-risk ratio?', '1:1.', ['2:1.', '1:2.', '3:1.'], 'Equal reward and risk creates a 1:1 ratio.'),
            $this->q('Expectancy', 'risk', 'A strategy wins 40% with average win $300 and average loss $100. What is the expectancy?', '+$60 per trade.', ['-$60 per trade.', '+$200 per trade.', '$0 per trade.'], 'Expectancy = (0.40 x 300) - (0.60 x 100) = 120 - 60 = 60.'),
            $this->q('Expectancy', 'risk', 'A strategy wins 70% with average win $80 and average loss $150. What is the expectancy?', '+$11 per trade.', ['-$11 per trade.', '+$70 per trade.', '-$150 per trade.'], 'Expectancy = (0.70 x 80) - (0.30 x 150) = 56 - 45 = 11.'),
            $this->q('Win Rate', 'foundation', 'A trader wins 18 trades out of 30. What is the win rate?', '60%.', ['40%.', '18%.', '30%.'], '18 divided by 30 equals 0.60, or 60%.'),
            $this->q('Drawdown', 'risk', 'An account falls from $12,000 equity peak to $10,800. What is the drawdown?', '10%.', ['5%.', '12%.', '20%.'], 'The decline is $1,200 from a $12,000 peak, which is 10%.'),
            $this->q('Trade Review', 'psychology', 'Which question is most useful after a losing trade?', 'Did I follow my plan and was the setup valid?', ['How can I win it back immediately?', 'Which random indicator should I add?', 'How can I hide it from the journal?'], 'Review should focus on process quality, not emotional recovery.'),
            $this->q('Scaling In', 'risk', 'What must be checked before adding to a winning position?', 'The new total risk and invalidation level.', ['Only the previous profit.', 'Only the candle color.', 'Only the account nickname.'], 'Adding size changes total exposure and must fit the risk plan.'),
            $this->q('Correlation Risk', 'risk', 'Opening long EUR/USD and long GBP/USD at the same time may create what issue?', 'Correlated USD exposure.', ['No market exposure.', 'Guaranteed hedging.', 'Zero drawdown risk.'], 'Pairs can share common drivers, increasing hidden concentration risk.'),
            $this->q('Weekend Risk', 'risk', 'Why might swing traders reduce size before the weekend?', 'Weekend gaps can create slippage beyond planned stops.', ['Weekend markets guarantee profit.', 'All positions earn positive swap.', 'Stops become more accurate.'], 'Closed markets can reopen at different prices.'),
            $this->q('Data Integrity', 'platform', 'Why should a trader include missed trades in review notes?', 'Missed trades reveal execution and psychology patterns.', ['Only filled trades matter for improvement.', 'Missed trades are always irrelevant.', 'They increase account balance.'], 'Missed valid setups can show hesitation or process issues.'),
            $this->q('Trade Frequency', 'psychology', 'A trader forces trades because they feel bored. What is the likely issue?', 'Overtrading driven by emotion.', ['Healthy diversification.', 'Correct position sizing.', 'A platform requirement.'], 'Boredom trades usually lack planned edge.'),
            $this->q('Confirmation Bias', 'psychology', 'A trader only looks for analysis that supports an existing trade idea. What bias is this?', 'Confirmation bias.', ['Loss aversion only.', 'Position sizing.', 'Execution slippage.'], 'Confirmation bias filters out opposing evidence.'),
            $this->q('Loss Aversion', 'psychology', 'A trader refuses to close a clearly invalid trade because accepting loss feels painful. What bias is involved?', 'Loss aversion.', ['Positive expectancy.', 'Market neutrality.', 'Healthy risk control.'], 'Loss aversion can make traders hold losers beyond the plan.'),
            $this->q('Routine', 'psychology', 'Why can a pre-market routine improve performance?', 'It prepares analysis, risk limits, and mental state before execution.', ['It predicts exact price movement.', 'It removes losing trades.', 'It changes broker fees.'], 'Routine supports consistency before decisions become emotional.'),
            $this->q('Post-Trade Grade', 'psychology', 'What should a post-trade grade measure first?', 'Quality of execution versus the trading plan.', ['Only profit or loss.', 'Only how fast the trade was opened.', 'Only the number of chart colors.'], 'Process grade helps improve repeatable behavior.'),
            $this->q('Invalidation', 'risk', 'What does trade invalidation mean?', 'The market condition that proves the trade idea is no longer valid.', ['The guaranteed target price.', 'The broker fee schedule.', 'The platform login status.'], 'Knowing invalidation helps place stops logically.'),
            $this->q('Opportunity Cost', 'foundation', 'Why should traders avoid tying margin in low-quality trades?', 'It can reduce ability to take higher-quality setups.', ['It guarantees better discipline.', 'It removes all costs.', 'It increases available margin.'], 'Capital and attention are limited resources.'),
            $this->q('Execution Quality', 'platform', 'What is one sign of poor execution discipline?', 'Entering far from the planned price without a valid reason.', ['Waiting for planned entry.', 'Recording slippage.', 'Reducing size during news.'], 'Poor entry discipline can damage reward-to-risk.'),
            $this->q('Risk Stacking', 'risk', 'Three trades all depend on USD weakness. What is the key risk?', 'The trades may behave like one larger USD bet.', ['They perfectly hedge each other.', 'They remove correlation.', 'They cannot lose together.'], 'Related positions can stack the same macro exposure.'),
            $this->q('Account Preservation', 'risk', 'Why is capital preservation a first priority?', 'Without capital, a trader cannot continue executing an edge.', ['It guarantees no losses.', 'It replaces skill development.', 'It means never taking trades.'], 'Protecting capital keeps the trader in the game long enough to improve.'),
            $this->q('Learning Goal', 'foundation', 'Why are daily exam questions useful even if not compulsory?', 'They reinforce concepts through repeated low-pressure review.', ['They guarantee live trading profits.', 'They replace all chart practice.', 'They remove the need for risk controls.'], 'Small daily review builds familiarity and retention over time.'),
            $this->q('Review Cadence', 'psychology', 'How often should an active trader review recent trades?', 'Regularly, using a consistent schedule.', ['Only after a big win.', 'Never, if the strategy is profitable.', 'Only when the broker asks.'], 'Scheduled review helps identify patterns early.'),
            $this->q('Rule Breach', 'psychology', 'What should happen after a trader breaks a major risk rule?', 'Pause, document it, and reduce the chance of repeating it.', ['Ignore it if the trade won.', 'Increase size immediately.', 'Delete the rule.'], 'Rule breaches are process risks and should be addressed directly.'),
            $this->q('Learning from Wins', 'psychology', 'Why review winning trades too?', 'Wins can reveal whether profit came from good process or luck.', ['Winning trades are never mistakes.', 'Wins do not need records.', 'All wins prove strategy edge.'], 'Some wins come from poor process, so they still deserve review.'),
            $this->q('Market Conditions', 'foundation', 'Why should a trader label market conditions in the journal?', 'Strategies can perform differently in trend, range, and high-volatility markets.', ['Market condition labels guarantee entries.', 'They replace stop losses.', 'They remove emotional bias completely.'], 'Context helps evaluate where a strategy works best.'),
            $this->q('Professional Mindset', 'psychology', 'Which mindset is healthiest for long-term development?', 'Focus on repeatable process and controlled risk.', ['Focus only on one huge win.', 'Avoid all losses forever.', 'Trade larger after frustration.'], 'Professional trading emphasizes process, risk, and continuous review.'),
        ];
    }

    private function q(string $category, string $difficulty, string $text, string $correct, array $incorrect, string $explanation): array
    {
        $incorrect = collect($incorrect)
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn ($value): bool => $value !== '' && $value !== $correct)
            ->unique()
            ->take(3)
            ->values()
            ->all();

        while (count($incorrect) < 3) {
            $incorrect[] = 'Not enough information to make that conclusion.';
            $incorrect = array_values(array_unique($incorrect));
        }

        $correctPosition = (int) (crc32($text) % 4);
        $options = $incorrect;
        array_splice($options, $correctPosition, 0, [$correct]);

        return [
            'category' => $category,
            'difficulty' => $difficulty,
            'question_text' => $text,
            'options' => $options,
            'correct_option' => $correctPosition,
            'explanation' => $explanation,
        ];
    }

    private function difficultyForConcept(string $concept): string
    {
        if (in_array($concept, ['Stop-loss order', 'Take-profit order', 'Position sizing', 'Risk per trade', 'Drawdown', 'Risk of ruin'], true)) {
            return 'risk';
        }

        if (in_array($concept, ['Support', 'Resistance', 'Trendline', 'Breakout', 'Pullback', 'Moving average', 'RSI', 'MACD', 'ATR', 'Divergence'], true)) {
            return 'technical';
        }

        if (in_array($concept, ['Overtrading', 'Revenge trading', 'Trading plan', 'Trade journal'], true)) {
            return 'psychology';
        }

        return 'foundation';
    }

    private function ratioLabel(float $ratio): string
    {
        return rtrim(rtrim(number_format($ratio, 2), '0'), '.') . ':1';
    }

    private function numberLabel(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2), '0'), '.');
    }

    private function money(float $amount): string
    {
        return '$' . rtrim(rtrim(number_format($amount, 2), '0'), '.');
    }
}
