<?php

namespace App\Services\Ai;

use App\Services\MarketInsight\MarketInsightService;
use App\Support\TradingSubmodules;
use Throwable;

/** Reads only current, calculated REAL Market Insight results; never fetches a quote during drafting. */
final class MarketAnalystVerifiedContextProvider
{
    public function __construct(private readonly MarketInsightService $insights)
    {
    }

    public function forSymbol(?string $symbol): array
    {
        if (strtoupper((string) preg_replace('/[^a-z0-9]/i', '', $symbol ?? '')) !== 'XAUUSD'
            || config('market_data.mode') !== 'REAL'
            || ! TradingSubmodules::enabled('ai_market_insight')) {
            return [];
        }

        try {
            $instrument = $this->insights->instrument('XAUUSD');
            $frames = [];
            foreach (['M15', 'H1', 'H4'] as $timeframe) {
                $analysis = $this->insights->latest($instrument, $timeframe);
                if (($analysis['status'] ?? null) !== 'available'
                    || ($analysis['source'] ?? null) !== 'REAL'
                    || ($analysis['is_mock_data'] ?? true)) {
                    continue;
                }

                $indicators = [];
                foreach (['rsi', 'ema', 'macd', 'bollinger', 'atr', 'adr'] as $name) {
                    $indicator = $analysis['indicators'][$name] ?? null;
                    if (! is_array($indicator) || ! ($indicator['available'] ?? false)) {
                        continue;
                    }
                    $indicators[$name] = ['classification' => (string) ($indicator['classification'] ?? 'Neutral')];
                    if ($name === 'rsi' && is_numeric($indicator['values']['rsi'] ?? null)) {
                        $indicators[$name]['value'] = number_format((float) $indicator['values']['rsi'], 2, '.', '');
                    }
                }

                if ($indicators) {
                    $frames[$timeframe] = [
                        'observed_at' => $analysis['market_data_at'] ?? null,
                        'indicators' => $indicators,
                    ];
                }
            }

            return $frames ? ['source' => 'Twelve Data completed candles', 'frames' => $frames] : [];
        } catch (Throwable $exception) {
            // Drafting from the form remains available if the optional market-data pipeline is unavailable.
            report($exception);

            return [];
        }
    }
}
