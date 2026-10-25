<?php

namespace App\Services\MarketInsight;

final class TechnicalIndicatorService
{
    public function calculate(array $chart, array $daily, array $settings): array
    {
        $rules = $settings['indicators'];
        $methods = ['bollinger' => 'bollinger', 'ema' => 'ema', 'macd' => 'macd',
            'rsi' => 'rsi', 'atr' => 'atr', 'adr' => 'adr'];
        $results = [];
        foreach ($methods as $key => $method) {
            $results[$key] = ! $rules[$key]['enabled']
                ? $this->result($key, 'Disabled', 0, [], 'Disabled in configuration.', $rules[$key], false)
                : ($key === 'adr' ? $this->$method($daily, $chart, $rules[$key]) : $this->$method($chart, $rules[$key]));
        }

        return $results;
    }

    public function bollinger(array $candles, array $settings): array
    {
        $period = (int) $settings['period'];
        $closes = array_column($candles, 'close');
        if (count($closes) < $period + 1) {
        return $this->unavailable('bollinger', $settings);
        }
        $bands = [];
        for ($i = $period - 1; $i < count($closes); $i++) {
            $window = array_slice($closes, $i - $period + 1, $period);
            $middle = array_sum($window) / $period;
            $variance = array_sum(array_map(fn ($price) => ($price - $middle) ** 2, $window)) / $period;
            $std = sqrt(max(0, $variance));
            $upper = $middle + (float) $settings['multiplier'] * $std;
            $lower = $middle - (float) $settings['multiplier'] * $std;
            $bands[$i] = ['upper' => $upper, 'middle' => $middle, 'lower' => $lower,
                'width' => $middle > 0 ? ($upper - $lower) / $middle * 100 : null,
                'percent_b' => $upper > $lower ? ($closes[$i] - $lower) / ($upper - $lower) : null];
        }
        $lastIndex = count($closes) - 1;
        $current = $bands[$lastIndex];
        $previous = $bands[$lastIndex - 1];
        $direction = $closes[$lastIndex - 1] < $previous['lower'] && $closes[$lastIndex] >= $current['lower'] ? 1
            : ($closes[$lastIndex - 1] > $previous['upper'] && $closes[$lastIndex] <= $current['upper'] ? -1 : 0);
        $widths = array_column(array_slice(array_values($bands), -11, 10), 'width');
        $baseline = $widths ? array_sum($widths) / count($widths) : null;
        $expansion = $current['width'] > $previous['width'] ? 'Expanding' : ($current['width'] < $previous['width'] ? 'Contracting' : 'Stable');
        $position = $closes[$lastIndex] > $current['upper'] ? 'Above upper band'
            : ($closes[$lastIndex] < $current['lower'] ? 'Below lower band' : 'Inside bands');
        $values = [...$current, 'expansion' => $expansion, 'squeeze' => $baseline && $current['width'] < $baseline * 0.65,
            'position' => $position, 'previous_close' => $closes[$lastIndex - 1]];
        $reason = $direction === 1 ? 'Previous close was below its lower band; latest close returned inside.'
            : ($direction === -1 ? 'Previous close was above its upper band; latest close returned inside.'
                : 'No completed-candle re-entry across either band; band contact alone is not a directional vote.');

        return $this->result('bollinger', $direction === 1 ? 'Bullish' : ($direction === -1 ? 'Bearish' : 'Neutral'), $direction, $values,
            $reason.' Bands are '.$expansion.'; price is '.$position.'.', $settings);
    }

    public function ema(array $candles, array $settings): array
    {
        $fast = $this->emaSeries(array_column($candles, 'close'), (int) $settings['fast']);
        $slow = $this->emaSeries(array_column($candles, 'close'), (int) $settings['slow']);
        $i = count($candles) - 1;
        if ($i < 1 || ! isset($slow[$i], $slow[$i - 1], $fast[$i], $fast[$i - 1])) {
        return $this->unavailable('ema', $settings);
        }
        $separation = $fast[$i] - $slow[$i];
        $prior = $fast[$i - 1] - $slow[$i - 1];
        $direction = $separation > 0 ? 1 : ($separation < 0 ? -1 : 0);
        $crossover = $prior <= 0 && $separation > 0 ? 'Bullish crossover'
            : ($prior >= 0 && $separation < 0 ? 'Bearish crossover' : 'None');

        return $this->result('ema', $direction === 1 ? 'Bullish' : ($direction === -1 ? 'Bearish' : 'Neutral'), $direction,
            ['fast' => $fast[$i], 'slow' => $slow[$i], 'separation' => $separation,
                'fast_slope' => $fast[$i] - $fast[$i - 1], 'slow_slope' => $slow[$i] - $slow[$i - 1],
                'crossover' => $crossover, 'trend' => $direction === 1 ? 'Uptrend' : ($direction === -1 ? 'Downtrend' : 'Flat')],
            'Fast EMA is '.($direction === 1 ? 'above' : ($direction === -1 ? 'below' : 'equal to')).' slow EMA. Latest crossover: '.$crossover.'.', $settings);
    }

    public function macd(array $candles, array $settings): array
    {
        $closes = array_column($candles, 'close');
        $fast = $this->emaSeries($closes, (int) $settings['fast']);
        $slow = $this->emaSeries($closes, (int) $settings['slow']);
        $line = [];
        foreach ($closes as $i => $_) {
        $line[$i] = isset($slow[$i], $fast[$i]) ? $fast[$i] - $slow[$i] : null;
        }
        $first = (int) $settings['slow'] - 1;
        $compact = array_slice($line, $first);
        $signalCompact = $this->emaSeries($compact, (int) $settings['signal']);
        $signal = array_fill(0, $first, null);
        foreach ($signalCompact as $value) {
        $signal[] = $value;
        }
        $i = count($closes) - 1;
        if ($i < 1 || ! isset($line[$i], $line[$i - 1], $signal[$i], $signal[$i - 1])) {
        return $this->unavailable('macd', $settings);
        }
        $histogram = $line[$i] - $signal[$i];
        $priorHistogram = $line[$i - 1] - $signal[$i - 1];
        $direction = $histogram > 0 ? 1 : ($histogram < 0 ? -1 : 0);
        $crossover = $priorHistogram <= 0 && $histogram > 0 ? 'Bullish crossover'
            : ($priorHistogram >= 0 && $histogram < 0 ? 'Bearish crossover' : 'None');
        $histogramDirection = $histogram > $priorHistogram ? 'Rising' : ($histogram < $priorHistogram ? 'Falling' : 'Flat');

        return $this->result('macd', $direction === 1 ? 'Bullish' : ($direction === -1 ? 'Bearish' : 'Neutral'), $direction,
            ['line' => $line[$i], 'signal' => $signal[$i], 'histogram' => $histogram,
                'histogram_direction' => $histogramDirection, 'momentum' => abs($histogram) > abs($priorHistogram) ? 'Strengthening' : 'Weakening',
                'crossover' => $crossover],
            'MACD line is '.($direction === 1 ? 'above' : ($direction === -1 ? 'below' : 'equal to')).' the signal line; histogram is '.$histogramDirection.'.', $settings);
    }

    public function rsi(array $candles, array $settings): array
    {
        $period = (int) $settings['period'];
        $closes = array_column($candles, 'close');
        if (count($closes) < $period + 2) {
        return $this->unavailable('rsi', $settings);
        }
        $gains = $losses = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $gains += max(0, $change);
            $losses += max(0, -$change);
        }
        $averageGain = $gains / $period;
        $averageLoss = $losses / $period;
        $previous = $this->rsiValue($averageGain, $averageLoss);
        for ($i = $period + 1; $i < count($closes); $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $averageGain = ($averageGain * ($period - 1) + max(0, $change)) / $period;
            $averageLoss = ($averageLoss * ($period - 1) + max(0, -$change)) / $period;
            $current = $this->rsiValue($averageGain, $averageLoss);
            if ($i < count($closes) - 1) {
            $previous = $current;
            }
        }
        $current = $current ?? $previous;
        $oversold = (float) $settings['oversold'];
        $overbought = (float) $settings['overbought'];
        $direction = $previous < $oversold && $current >= $oversold ? 1
            : ($previous > $overbought && $current <= $overbought ? -1
                : ($current >= 55 && $current < $overbought ? 1 : ($current > $oversold && $current < 45 ? -1 : 0)));
        $classification = $current < $oversold ? 'Oversold warning' : ($current > $overbought ? 'Overbought warning'
            : ($current >= 45 && $current <= 55 ? 'Neutral momentum'
                : ($direction > 0 ? 'Positive momentum' : ($direction < 0 ? 'Negative momentum' : 'No directional confirmation'))));

        return $this->result('rsi', $classification, $direction,
            ['rsi' => $current, 'previous_rsi' => $previous,
                'caution' => $current < $oversold || $current > $overbought ? $classification : null],
            $direction === 1 ? 'RSI confirms positive momentum or recovered through oversold.'
                : ($direction === -1 ? 'RSI confirms negative momentum or fell through overbought.'
                    : 'RSI adds no directional vote; overbought/oversold alone is a caution.'), $settings);
    }

    public function atr(array $candles, array $settings): array
    {
        $period = (int) $settings['period'];
        $baselinePeriod = (int) $settings['baseline_period'];
        if (count($candles) < $period + $baselinePeriod + 1) {
        return $this->unavailable('atr', $settings);
        }
        $ranges = [];
        for ($i = 1; $i < count($candles); $i++) {
            $ranges[$i] = max($candles[$i]['high'] - $candles[$i]['low'],
                abs($candles[$i]['high'] - $candles[$i - 1]['close']),
                abs($candles[$i]['low'] - $candles[$i - 1]['close']));
        }
        $value = array_sum(array_slice($ranges, 0, $period)) / $period;
        $series = [$period => $value];
        for ($i = $period + 1; $i < count($candles); $i++) {
            $value = ($value * ($period - 1) + $ranges[$i]) / $period;
            $series[$i] = $value;
        }
        $i = count($candles) - 1;
        $normalized = $value / $candles[$i]['close'] * 100;
        $priorPercents = [];
        for ($j = $i - $baselinePeriod; $j < $i; $j++) {
        $priorPercents[] = $series[$j] / $candles[$j]['close'] * 100;
        }
        $baseline = array_sum($priorPercents) / count($priorPercents);
        $ratio = $baseline > 0 ? $normalized / $baseline : 1;
        $classification = $ratio >= (float) $settings['high_ratio'] ? 'High Volatility'
            : ($ratio <= (float) $settings['low_ratio'] ? 'Low Volatility' : 'Normal Volatility');

        return $this->result('atr', $classification, 0,
            ['atr' => $value, 'atr_percent' => $normalized, 'baseline_percent' => $baseline,
                'baseline_ratio' => $ratio, 'expansion' => $value > $series[$i - 1] ? 'Expanding' : 'Contracting',
                'risk_warning' => $classification === 'High Volatility' ? 'Volatility is unusually high.' : null],
            'ATR measures range and gap volatility; it is not a BUY or SELL vote. Current volatility is '.$classification.'.', $settings);
    }

    public function adr(array $daily, array $chart, array $settings): array
    {
        $period = (int) $settings['period'];
        $sessions = array_values(array_filter($daily, fn ($c) => ($c['is_complete'] ?? false) === true));
        if (count($sessions) < $period || $chart === []) {
        return $this->unavailable('adr', $settings);
        }
        $window = array_slice($sessions, -$period);
        $adr = array_sum(array_map(fn ($c) => $c['high'] - $c['low'], $window)) / $period;
        $lastDaily = end($sessions);
        $latest = end($chart);
        $day = $latest['session_date'] ?? substr($latest['open_at'], 0, 10);
        $current = array_values(array_filter($chart, fn ($c) => ($c['session_date'] ?? substr($c['open_at'], 0, 10)) === $day));
        $sessionRange = $current && $latest['timeframe'] !== 'D1'
            ? max(array_column($current, 'high')) - min(array_column($current, 'low')) : null;
        $ratio = $sessionRange !== null && $adr > 0 ? $sessionRange / $adr : null;
        $status = $ratio === null ? 'Session range unavailable'
            : ($ratio >= (float) $settings['extended_ratio'] ? 'Extended Daily Range'
                : ($ratio < (float) $settings['low_ratio'] ? 'Below Normal Daily Range' : 'Normal Daily Range'));

        return $this->result('adr', $status, 0,
            ['adr' => $adr, 'adr_percent' => $lastDaily['close'] > 0 ? $adr / $lastDaily['close'] * 100 : null,
                'current_session_range' => $sessionRange, 'session_usage_percent' => $ratio === null ? null : $ratio * 100,
                'remaining_typical_range' => $sessionRange === null ? null : max(0, $adr - $sessionRange),
                'session_date' => $day,
                'calendar' => ($latest['source'] ?? 'MOCK') === 'REAL' ? 'Twelve Data daily session' : 'UTC demo sessions'],
            'ADR averages completed daily high-low ranges. '.$status.' is context, not a price limit or directional vote.', $settings);
    }

    public function emaSeries(array $values, int $period): array
    {
        $series = array_fill(0, count($values), null);
        if ($period < 2 || count($values) < $period) {
        return $series;
        }
        $value = array_sum(array_slice($values, 0, $period)) / $period;
        $series[$period - 1] = $value;
        $alpha = 2 / ($period + 1);
        for ($i = $period; $i < count($values); $i++) {
            $value += $alpha * ($values[$i] - $value);
            $series[$i] = $value;
        }

        return $series;
    }

    private function rsiValue(float $gain, float $loss): float
    {
        if ($gain == 0.0 && $loss == 0.0) {
        return 50.0;
        }
        if ($loss == 0.0) {
        return 100.0;
        }

        return 100 - 100 / (1 + $gain / $loss);
    }

    private function unavailable(string $key, array $settings): array
    {
        return $this->result($key, 'Unavailable', 0, [], 'Insufficient completed candle history.', $settings, false);
    }

    private function result(string $key, string $classification, int $direction, array $values,
        string $explanation, array $settings, bool $available = true): array
    {
        return ['name' => strtoupper($key), 'enabled' => (bool) ($settings['enabled'] ?? true),
            'available' => $available, 'classification' => $classification,
            'direction' => $direction, 'values' => $values, 'explanation' => $explanation,
            'configuration' => $settings];
    }
}
