<?php

namespace App\Support;

class PmsPriority
{
    public const OPTIONS = ['P4' => 'Low', 'P3' => 'Normal', 'P2' => 'High', 'P1' => 'Critical'];

    public static function label(?string $code): string
    {
        return $code === 'P0' ? 'Critical' : (self::OPTIONS[$code] ?? 'Normal');
    }

    public static function codesFor(string $label): array
    {
        return match ($label) {
            'Low' => ['P4'], 'Normal' => ['P3'], 'High' => ['P2'], 'Critical' => ['P0', 'P1'],
            default => [],
        };
    }
}
