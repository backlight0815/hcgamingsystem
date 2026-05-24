<?php

namespace App\Services;

use Carbon\Carbon;

class TradingJournalTimeService
{
    public const TIMEZONE_MALAYSIA = 'malaysia';
    public const TIMEZONE_MT5 = 'mt5';

    private const MALAYSIA_OFFSET_MINUTES = 480;
    private const DEFAULT_MT5_OFFSET_MINUTES = 180;

    public function modes(): array
    {
        return [
            self::TIMEZONE_MALAYSIA => [
                'label' => 'Malaysia Time',
                'short_label' => 'MYT',
                'description' => 'UTC+8',
            ],
            self::TIMEZONE_MT5 => [
                'label' => 'MT5 Platform Time',
                'short_label' => 'MT5',
                'description' => 'server offset selected below',
            ],
        ];
    }

    public function mt5OffsetOptions(): array
    {
        return [
            120 => 'UTC+2',
            180 => 'UTC+3',
        ];
    }

    public function normalizeMode(?string $mode): string
    {
        return array_key_exists((string) $mode, $this->modes())
            ? (string) $mode
            : self::TIMEZONE_MALAYSIA;
    }

    public function normalizeOffset($offsetMinutes, ?string $mode = self::TIMEZONE_MT5): int
    {
        if ($this->normalizeMode($mode) === self::TIMEZONE_MALAYSIA) {
            return self::MALAYSIA_OFFSET_MINUTES;
        }

        if ($offsetMinutes === null || $offsetMinutes === '') {
            return self::DEFAULT_MT5_OFFSET_MINUTES;
        }

        $offsetMinutes = (int) $offsetMinutes;

        return array_key_exists($offsetMinutes, $this->mt5OffsetOptions())
            ? $offsetMinutes
            : self::DEFAULT_MT5_OFFSET_MINUTES;
    }

    public function offsetLabel($offsetMinutes, ?string $mode = self::TIMEZONE_MT5): string
    {
        if ($this->normalizeMode($mode) === self::TIMEZONE_MALAYSIA) {
            return 'UTC+8';
        }

        $offsetMinutes = $this->normalizeOffset($offsetMinutes, $mode);

        return $this->mt5OffsetOptions()[$offsetMinutes] ?? 'UTC+3';
    }

    public function label(?string $mode, $offsetMinutes = null): string
    {
        $mode = $this->normalizeMode($mode);

        if ($mode === self::TIMEZONE_MT5) {
            return $this->modes()[$mode]['label'] . ' (' . $this->offsetLabel($offsetMinutes, $mode) . ')';
        }

        return $this->modes()[$mode]['label'] . ' (UTC+8)';
    }

    public function shortLabel(?string $mode, $offsetMinutes = null): string
    {
        $mode = $this->normalizeMode($mode);

        if ($mode === self::TIMEZONE_MT5) {
            return 'MT5 ' . $this->offsetLabel($offsetMinutes, $mode);
        }

        return $this->modes()[$mode]['short_label'];
    }

    public function toMalaysiaCarbon($value, ?string $sourceMode, $sourceOffsetMinutes = null): ?Carbon
    {
        $date = $this->parseNaive($value);

        if (! $date) {
            return null;
        }

        return $date->addMinutes(self::MALAYSIA_OFFSET_MINUTES - $this->offsetMinutes($sourceMode, $sourceOffsetMinutes));
    }

    public function toMalaysiaDatabase($value, ?string $sourceMode, $sourceOffsetMinutes = null): ?string
    {
        return $this->toMalaysiaCarbon($value, $sourceMode, $sourceOffsetMinutes)?->format('Y-m-d H:i:s');
    }

    public function fromMalaysiaCarbon($value, ?string $targetMode, $targetOffsetMinutes = null): ?Carbon
    {
        $date = $this->parseNaive($value);

        if (! $date) {
            return null;
        }

        return $date->addMinutes($this->offsetMinutes($targetMode, $targetOffsetMinutes) - self::MALAYSIA_OFFSET_MINUTES);
    }

    public function formatForInput($value, ?string $targetMode, $targetOffsetMinutes = null): string
    {
        return $this->fromMalaysiaCarbon($value, $targetMode, $targetOffsetMinutes)?->format('Y-m-d\TH:i') ?? '';
    }

    public function formatForDisplay($value, ?string $targetMode, $targetOffsetMinutes = null): string
    {
        return $this->fromMalaysiaCarbon($value, $targetMode, $targetOffsetMinutes)?->format('Y-m-d H:i') ?? 'N/A';
    }

    public function convertDisplayPair($value, $mt5OffsetMinutes = null): array
    {
        return [
            self::TIMEZONE_MALAYSIA => $this->formatForDisplay($value, self::TIMEZONE_MALAYSIA),
            self::TIMEZONE_MT5 => $this->formatForDisplay($value, self::TIMEZONE_MT5, $mt5OffsetMinutes),
        ];
    }

    private function offsetMinutes(?string $mode, $offsetMinutes = null): int
    {
        return $this->normalizeMode($mode) === self::TIMEZONE_MT5
            ? $this->normalizeOffset($offsetMinutes, $mode)
            : self::MALAYSIA_OFFSET_MINUTES;
    }

    private function parseNaive($value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            if ($value instanceof Carbon) {
                return Carbon::parse($value->format('Y-m-d H:i:s'), 'UTC');
            }

            return Carbon::parse(str_replace('T', ' ', (string) $value), 'UTC');
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
