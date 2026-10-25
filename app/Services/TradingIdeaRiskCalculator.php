<?php

namespace App\Services;

use InvalidArgumentException;

/** Pure calculations; instrument values must come from a verified specification. */
class TradingIdeaRiskCalculator
{
    public function evaluate(array $idea, float $maximumRiskPercent = 1, float $minimumRewardRatio = 2): array
    {
        $equity = (float) ($idea['equity'] ?? 0);
        $riskPercent = (float) ($idea['risk_percent'] ?? 0.5);
        if ($equity <= 0 || $riskPercent <= 0 || $riskPercent > min(1, $maximumRiskPercent)) {
            throw new InvalidArgumentException('Equity must be positive and total idea risk must be within the configured limit.');
        }
        $budget = $equity * $riskPercent / 100;
        $committed = (float) ($idea['existing_idea_risk'] ?? 0);
        if ($committed < 0 || $committed >= $budget) {
            throw new InvalidArgumentException('Existing positions exhaust the risk budget for this idea.');
        }
        $result = ['risk_budget' => round($budget, 2), 'remaining_risk' => round($budget - $committed, 2),
            'risk_percent' => $riskPercent, 'position_size' => null, 'reward_ratio' => null,
            'classification' => 'Insufficient data', 'warnings' => []];
        if (! isset($idea['entry'], $idea['stop'], $idea['side'])) return $result;
        $entry = (float) $idea['entry'];
        $stop = (float) $idea['stop'];
        $side = $idea['side'];
        $distance = abs($entry - $stop);
        if ($distance <= 0 || ! in_array($side, ['buy', 'sell'], true) || ($side === 'buy' && $stop >= $entry) || ($side === 'sell' && $stop <= $entry)) {
            throw new InvalidArgumentException('The stop must be beyond structural invalidation on the loss side of entry.');
        }
        $result['stop_distance'] = $distance;
        $result['classification'] = 'Wait for confirmation';
        $target = isset($idea['opposing_level']) ? (float) $idea['opposing_level'] : null;
        if ($target !== null) {
            $reward = $side === 'buy' ? $target - $entry : $entry - $target;
            $result['reward_ratio'] = round($reward / $distance, 2);
            $result['classification'] = $reward <= 0 || $reward / $distance < $minimumRewardRatio ? 'No Trade' : 'Educational scenario';
            if ($result['classification'] === 'No Trade') $result['warnings'][] = 'The nearest opposing level does not offer the configured minimum reward-to-risk target.';
        } else {
            $result['warnings'][] = 'An opposing structural level is needed before evaluating a target.';
        }
        $tickSize = (float) ($idea['tick_size'] ?? 0);
        $tickValue = (float) ($idea['tick_value'] ?? 0);
        $conversion = (float) ($idea['currency_conversion'] ?? 1);
        $costPerUnit = (float) ($idea['cost_per_unit'] ?? 0);
        if ($tickSize <= 0 || $tickValue <= 0 || $conversion <= 0) {
            $result['warnings'][] = 'Verified tick size, tick value and any currency conversion are required for position sizing; contract size alone is insufficient.';
            return $result;
        }
        $lossPerUnit = $distance / $tickSize * $tickValue * $conversion + $costPerUnit;
        $rawSize = ($budget - $committed) / $lossPerUnit;
        $step = (float) ($idea['volume_step'] ?? 0);
        $minimum = (float) ($idea['minimum_volume'] ?? 0);
        if ($step <= 0 || $minimum <= 0) {
            $result['warnings'][] = 'Verified minimum volume and volume step are required for compliant sizing.';
            return $result;
        }
        $size = floor(($rawSize + 1e-10) / $step) * $step;
        if ($size + 1e-10 < $minimum) {
            $result['classification'] = 'No Trade';
            $result['warnings'][] = 'Minimum tradable volume exceeds the idea risk limit.';
            return $result;
        }
        $result['position_size'] = round($size, 8);
        $result['planned_loss'] = round($size * $lossPerUnit + $committed, 2);
        $result['warnings'][] = 'Spread, slippage and stop execution can make realized loss exceed the plan.';
        return $result;
    }

    public function breakEven(float $entry, string $side, float $totalCosts, float $units, float $valuePerPriceUnit): float
    {
        if ($units <= 0 || $valuePerPriceUnit <= 0 || $totalCosts < 0 || ! in_array($side, ['buy', 'sell'], true)) {
            throw new InvalidArgumentException('Valid units, price value, costs and side are required.');
        }
        $offset = $totalCosts / ($units * $valuePerPriceUnit);
        return $side === 'buy' ? $entry + $offset : $entry - $offset;
    }
}
