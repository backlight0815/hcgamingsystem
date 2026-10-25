<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class PmsDateTime
{
    public const ZONE = 'Asia/Kuala_Lumpur';

    public static function format(CarbonInterface|string|null $value, bool $zone = false, ?string $sourceZone = null): string
    {
        if ($value === null || $value === '') return 'Not set';
        $date = $value instanceof CarbonInterface ? $value->copy() : Carbon::parse($value, $sourceZone ?: config('app.timezone', 'UTC'));

        return $date->timezone(self::ZONE)->format('d M Y, g:i A').($zone ? ' MYT' : '');
    }

    public static function csv(CarbonInterface|string|null $value): string
    {
        return $value ? self::format($value, true) : '';
    }

    public static function date(CarbonInterface|string|null $value): string
    {
        if (! $value) return '';
        $date = $value instanceof CarbonInterface ? $value->copy() : Carbon::parse($value, config('app.timezone', 'UTC'));
        return $date->timezone(self::ZONE)->format('d M Y');
    }

    public static function time(CarbonInterface|string|null $value): string
    {
        if (! $value) return '';
        $date = $value instanceof CarbonInterface ? $value->copy() : Carbon::parse($value, config('app.timezone', 'UTC'));
        return $date->timezone(self::ZONE)->format('h:i A').' MYT';
    }
}
