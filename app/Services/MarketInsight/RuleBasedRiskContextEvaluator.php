<?php

namespace App\Services\MarketInsight;

final class RuleBasedRiskContextEvaluator implements RiskContextEvaluator
{
    public function evaluate(array $indicators, array $factors): array
    {
        $atr = $indicators['atr'];
        $adr = $indicators['adr'];
        $warnings = [];
        if ($atr['enabled'] && $atr['classification'] === 'High Volatility') {
            $warnings[] = 'ATR volatility is unusually high.';
        }
        if ($adr['enabled'] && $adr['classification'] === 'Extended Daily Range') {
            $warnings[] = 'The current UTC demo session exceeds its typical daily range.';
        }
        if ($factors['bullish'] && $factors['bearish']) {
            $warnings[] = 'Directional indicators disagree.';
        }

        return [
            'risk' => $warnings ? 'Elevated' : 'Normal',
            'volatility' => $atr['enabled'] ? $atr['classification'] : 'Disabled',
            'daily_range_status' => $adr['enabled'] ? $adr['classification'] : 'Disabled',
            'warnings' => $warnings,
        ];
    }
}
