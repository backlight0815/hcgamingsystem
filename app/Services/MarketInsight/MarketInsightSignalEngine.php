<?php

namespace App\Services\MarketInsight;

final class MarketInsightSignalEngine
{
    public const VERSION = 'rule-v1';

    public function __construct(private readonly RiskContextEvaluator $riskEvaluator,
        private readonly InsightExplanationGenerator $explanations)
    {
        }

    public function combine(array $indicators, array $settings): array
    {
        $weightSum = 0.0;
        foreach (MarketInsightSettings::DIRECTIONAL as $key) {
            if ($indicators[$key]['enabled']) {
            $weightSum += (float) $settings['indicators'][$key]['weight'];
            }
        }
        if ($weightSum <= 0) {
        return ['status' => 'unavailable', 'reason' => 'No positive directional weight is enabled.'];
        }
        foreach ($indicators as $item) {
            if ($item['enabled'] && ! $item['available']) {
                return ['status' => 'unavailable', 'reason' => $item['name'].' lacks sufficient validated candle history.'];
            }
        }
        $score = 0.0;
        $contributions = [];
        $factors = ['bullish' => [], 'bearish' => [], 'neutral' => []];
        foreach (MarketInsightSettings::DIRECTIONAL as $key) {
            if (! $indicators[$key]['enabled']) {
                $contributions[$key] = ['weight' => 0, 'points' => 0, 'direction' => 0];

                continue;
            }
            $weight = (float) $settings['indicators'][$key]['weight'] / $weightSum * 100;
            $direction = (int) $indicators[$key]['direction'];
            $points = $weight * $direction;
            $score += $points;
            $contributions[$key] = ['weight' => round($weight, 3), 'points' => round($points, 3), 'direction' => $direction];
            $factors[$direction > 0 ? 'bullish' : ($direction < 0 ? 'bearish' : 'neutral')][] = strtoupper($key);
        }
        $score = round(max(-100, min(100, $score)), 3);
        $t = $settings['thresholds'];
        $signal = $score <= (float) $t['strong_sell'] ? 'STRONG SELL'
            : ($score <= (float) $t['sell'] ? 'SELL'
                : ($score < (float) $t['buy'] ? 'NEUTRAL'
                    : ($score < (float) $t['strong_buy'] ? 'BUY' : 'STRONG BUY')));
        $sign = $score > 0 ? 1 : ($score < 0 ? -1 : 0);
        $agreeing = 0.0;
        foreach ($contributions as $item) {
            if ($sign !== 0 && $item['direction'] === $sign) {
            $agreeing += $item['weight'];
            }
        }
        $risk = $this->riskEvaluator->evaluate($indicators, $factors);
        $explanation = $this->explanations->generate($indicators, $factors, $risk, $score, $signal);

        return [
            'status' => 'available', 'method' => 'Rule-Based Indicator Analysis',
            'signal' => $signal, 'directional_score' => $score,
            'signal_strength' => abs($score), 'agreement_score' => round($agreeing, 2),
            'factor_counts' => array_map('count', $factors), 'factors' => $factors,
            'contributions' => $contributions, ...$risk, 'explanation' => $explanation,
            'data_quality' => 'validated_completed_mock',
        ];
    }
}
