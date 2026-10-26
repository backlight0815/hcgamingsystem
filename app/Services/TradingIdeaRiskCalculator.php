<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/** Pure decimal calculations; instrument values must come from a verified specification. */
class TradingIdeaRiskCalculator
{
    /** Price-distance R only; this cannot determine money risk or tradable volume. */
    public function priceDistanceR(string $entry, string $stop, string $observed, string $side): string
    {
        $entryPrice = $this->decimal($entry);
        $stopPrice = $this->decimal($stop);
        $observedPrice = $this->decimal($observed);
        if (! in_array($side, ['buy', 'sell'], true)
            || $entryPrice->isNegativeOrZero() || $stopPrice->isNegativeOrZero()
            || $observedPrice->isNegativeOrZero()
            || ($side === 'buy' && $stopPrice->isGreaterThanOrEqualTo($entryPrice))
            || ($side === 'sell' && $stopPrice->isLessThanOrEqualTo($entryPrice))) {
            throw new InvalidArgumentException('Entry, stop and side must describe a valid hypothetical position.');
        }
        $distance = $entryPrice->minus($stopPrice)->abs();
        $move = $side === 'buy' ? $observedPrice->minus($entryPrice) : $entryPrice->minus($observedPrice);
        return (string) $move->dividedBy($distance, 4, RoundingMode::HALF_UP)->stripTrailingZeros();
    }

    public function evaluate(array $idea, float $maximumRiskPercent = 1, float $minimumRewardRatio = 2): array
    {
        $equity = $this->decimal($idea['equity'] ?? 0);
        $riskPercent = $this->decimal($idea['risk_percent'] ?? '0.5');
        if ($equity->isNegativeOrZero() || $riskPercent->isNegativeOrZero()
            || $riskPercent->isGreaterThan(min(1, $maximumRiskPercent))) {
            throw new InvalidArgumentException('Equity must be positive and total idea risk must be within the configured limit.');
        }
        $budget = $equity->multipliedBy($riskPercent)->dividedBy(100, 16, RoundingMode::DOWN);
        $committed = $this->decimal($idea['existing_idea_risk'] ?? 0);
        if ($committed->isNegative() || $committed->isGreaterThanOrEqualTo($budget)) {
            throw new InvalidArgumentException('Existing positions exhaust the risk budget for this idea.');
        }
        $remaining = $budget->minus($committed);
        $result = [
            'risk_budget' => $this->rounded($budget, 2), 'remaining_risk' => $this->rounded($remaining, 2),
            'risk_percent' => $riskPercent->toFloat(), 'position_size' => null, 'reward_ratio' => null,
            'classification' => 'Insufficient data', 'warnings' => [],
        ];
        if (! isset($idea['entry'], $idea['stop'], $idea['side'])) return $result;
        $entry = $this->decimal($idea['entry']);
        $stop = $this->decimal($idea['stop']);
        $side = $idea['side'];
        $distance = $entry->minus($stop)->abs();
        if ($distance->isZero() || ! in_array($side, ['buy', 'sell'], true)
            || ($side === 'buy' && $stop->isGreaterThanOrEqualTo($entry))
            || ($side === 'sell' && $stop->isLessThanOrEqualTo($entry))) {
            throw new InvalidArgumentException('The stop must be beyond structural invalidation on the loss side of entry.');
        }
        $result['stop_distance'] = $distance->toFloat();
        $result['classification'] = 'Wait for confirmation';
        if (isset($idea['opposing_level'])) {
            $target = $this->decimal($idea['opposing_level']);
            $reward = $side === 'buy' ? $target->minus($entry) : $entry->minus($target);
            $ratio = $reward->dividedBy($distance, 12, RoundingMode::DOWN);
            $result['reward_ratio'] = $this->rounded($ratio, 2);
            $result['classification'] = $reward->isNegativeOrZero() || $ratio->isLessThan($minimumRewardRatio)
                ? 'No Trade' : 'Educational scenario';
            if ($result['classification'] === 'No Trade') {
                $result['warnings'][] = 'The nearest opposing level does not offer the configured minimum reward-to-risk target.';
            }
        } else {
            $result['warnings'][] = 'An opposing structural level is needed before evaluating a target.';
        }
        $tickSize = $this->decimal($idea['tick_size'] ?? 0);
        $tickValue = $this->decimal($idea['tick_value'] ?? 0);
        $conversion = $this->decimal($idea['currency_conversion'] ?? 1);
        $costPerUnit = $this->decimal($idea['cost_per_unit'] ?? 0);
        if ($tickSize->isNegativeOrZero() || $tickValue->isNegativeOrZero() || $conversion->isNegativeOrZero()) {
            $result['warnings'][] = 'Verified tick size, tick value and any currency conversion are required for position sizing; contract size alone is insufficient.';
            return $result;
        }
        $lossPerUnit = $distance->dividedBy($tickSize, 16, RoundingMode::UP)
            ->multipliedBy($tickValue)->multipliedBy($conversion)->plus($costPerUnit);
        $step = $this->decimal($idea['volume_step'] ?? 0);
        $minimum = $this->decimal($idea['minimum_volume'] ?? 0);
        if ($step->isNegativeOrZero() || $minimum->isNegativeOrZero()) {
            $result['warnings'][] = 'Verified minimum volume and volume step are required for compliant sizing.';
            return $result;
        }
        $rawSize = $remaining->dividedBy($lossPerUnit, 16, RoundingMode::DOWN);
        $size = $rawSize->dividedBy($step, 0, RoundingMode::DOWN)->multipliedBy($step);
        if ($size->isLessThan($minimum)) {
            $result['classification'] = 'No Trade';
            $result['warnings'][] = 'Minimum tradable volume exceeds the idea risk limit.';
            return $result;
        }
        $result['position_size'] = $size->toFloat();
        $result['planned_loss'] = $this->rounded($size->multipliedBy($lossPerUnit)->plus($committed), 2);
        $result['warnings'][] = 'Spread, slippage and stop execution can make realized loss exceed the plan.';
        return $result;
    }

    public function breakEven(float $entry, string $side, float $totalCosts, float $units, float $valuePerPriceUnit): float
    {
        if ($units <= 0 || $valuePerPriceUnit <= 0 || $totalCosts < 0 || ! in_array($side, ['buy', 'sell'], true)) {
            throw new InvalidArgumentException('Valid units, price value, costs and side are required.');
        }
        $offset = $this->decimal($totalCosts)->dividedBy(
            $this->decimal($units)->multipliedBy($this->decimal($valuePerPriceUnit)), 12, RoundingMode::UP);
        $price = $side === 'buy' ? $this->decimal($entry)->plus($offset) : $this->decimal($entry)->minus($offset);
        return $price->toFloat();
    }

    public function performance(float $netResult, float $plannedRisk, float $winRatePercent,
        float $averageWin, float $averageLoss, float $peakEquity, float $currentEquity,
        float $spread, float $units, float $valuePerPriceUnit): array
    {
        if ($plannedRisk <= 0 || $winRatePercent < 0 || $winRatePercent > 100
            || $averageWin < 0 || $averageLoss < 0 || $peakEquity <= 0
            || $currentEquity < 0 || $currentEquity > $peakEquity || $spread < 0
            || $units <= 0 || $valuePerPriceUnit <= 0) {
            throw new InvalidArgumentException('Valid risk, win rate, equity and contract values are required.');
        }
        $probability = $this->decimal($winRatePercent)->dividedBy(100, 12, RoundingMode::DOWN);
        $expectancy = $probability->multipliedBy($this->decimal($averageWin))
            ->minus(BigDecimal::one()->minus($probability)->multipliedBy($this->decimal($averageLoss)));
        $drawdown = $this->decimal($peakEquity)->minus($this->decimal($currentEquity))
            ->dividedBy($this->decimal($peakEquity), 12, RoundingMode::HALF_UP)->multipliedBy(100);
        return [
            'r_multiple' => $this->rounded($this->decimal($netResult)->dividedBy(
                $this->decimal($plannedRisk), 12, RoundingMode::HALF_UP), 4),
            'expectancy_per_trade' => $this->rounded($expectancy, 4),
            'drawdown_percent' => $this->rounded($drawdown, 4),
            'spread_cost' => $this->rounded($this->decimal($spread)->multipliedBy($this->decimal($units))
                ->multipliedBy($this->decimal($valuePerPriceUnit)), 4),
            'basis' => 'Illustrative calculation from supplied inputs; not verified journal or live market data.',
        ];
    }

    private function decimal(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) $value);
    }

    private function rounded(BigDecimal $value, int $scale): float
    {
        return $value->toScale($scale, RoundingMode::HALF_UP)->toFloat();
    }
}
