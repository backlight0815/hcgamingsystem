<?php

namespace App\Services\MarketInsight;

final class RuleBasedInsightExplanationGenerator implements InsightExplanationGenerator
{
    public function generate(array $indicators, array $factors, array $risk, float $score, string $signal): string
    {
        $parts = [];
        foreach (['bullish' => 'support bullish direction', 'bearish' => 'support bearish direction', 'neutral' => 'are neutral'] as $key => $phrase) {
            $parts[] = $factors[$key] ? implode(' and ', $factors[$key]).' '.$phrase : 'No enabled indicators '.$phrase;
        }
        $parts[] = $indicators['atr']['enabled'] ? 'ATR: '.$risk['volatility'].'.' : 'ATR is disabled.';
        $parts[] = $indicators['adr']['enabled'] ? 'ADR: '.$risk['daily_range_status'].'.' : 'ADR is disabled.';
        $parts[] = 'Weighted direction is '.$score.'/100, producing '.$signal.'.';
        if ($risk['warnings']) {
        $parts[] = 'Caution: '.implode(' ', $risk['warnings']);
        }

        return implode(' ', $parts);
    }
}
