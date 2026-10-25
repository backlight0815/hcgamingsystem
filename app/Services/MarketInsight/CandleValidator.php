<?php

namespace App\Services\MarketInsight;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class CandleValidator
{
    public function validate(array $candles, string $timeframe, string $source = 'MOCK'): void
    {
        $minutes = MarketInsightSettings::TIMEFRAMES[$timeframe] ?? null;
        if (! $minutes) {
        throw new InvalidArgumentException('Unsupported candle timeframe.');
        }
        $previousOpen = null;
        $previousClose = null;
        foreach ($candles as $candle) {
            foreach (['open', 'high', 'low', 'close'] as $key) {
                if (! isset($candle[$key]) || ! is_numeric($candle[$key]) ||
                    ! is_finite((float) $candle[$key]) || (float) $candle[$key] <= 0) {
                    throw new InvalidArgumentException('OHLC prices must be finite and positive.');
                }
            }
            $open = (float) $candle['open'];
            $high = (float) $candle['high'];
            $low = (float) $candle['low'];
            $close = (float) $candle['close'];
            if ($high < max($open, $close, $low) || $low > min($open, $close) || $low > $high) {
                throw new InvalidArgumentException('Candle high/low is inconsistent with open and close.');
            }
            if (isset($candle['volume']) && (! is_numeric($candle['volume']) ||
                ! is_finite((float) $candle['volume']) || (float) $candle['volume'] < 0)) {
                throw new InvalidArgumentException('Candle volume must be finite and nonnegative.');
            }
            if (! in_array($source, ['MOCK', 'REAL'], true) || ($candle['source'] ?? null) !== $source ||
                ($candle['is_complete'] ?? false) !== true) {
                throw new InvalidArgumentException('Only completed candles from one source are accepted.');
            }
            if (empty($candle['open_at']) || empty($candle['close_at'])) {
                throw new InvalidArgumentException('Candle opening and closing timestamps are required.');
            }
            try {
                $started = new DateTimeImmutable((string) $candle['open_at'], new DateTimeZone('UTC'));
                $ended = new DateTimeImmutable((string) $candle['close_at'], new DateTimeZone('UTC'));
            } catch (\Exception) {
                throw new InvalidArgumentException('Candle timestamps are invalid.');
            }
            $duration = $ended->getTimestamp() - $started->getTimestamp();
            $validDuration = match (true) {
                $source === 'REAL' && $timeframe === 'D1' => in_array($duration, [23 * 3600, 24 * 3600, 25 * 3600], true),
                $source === 'REAL' && $timeframe === 'H4' => $duration > 0 && $duration <= $minutes * 60,
                default => $duration === $minutes * 60,
            };
            if (! $validDuration) {
                throw new InvalidArgumentException('Candle duration is invalid.');
            }
            if ($source === 'REAL' && ($started->getTimestamp() > CarbonImmutable::now('UTC')->getTimestamp() + 60 ||
                $ended->getTimestamp() > CarbonImmutable::now('UTC')->getTimestamp() + 60)) {
                throw new InvalidArgumentException('Candle timestamp is in the future.');
            }
            if (($previousOpen !== null && $started->getTimestamp() <= $previousOpen) ||
                ($previousClose !== null && $started->getTimestamp() < $previousClose)) {
                throw new InvalidArgumentException('Candle timestamps are duplicated or unordered.');
            }
            $previousOpen = $started->getTimestamp();
            $previousClose = $ended->getTimestamp();
        }
    }
}
