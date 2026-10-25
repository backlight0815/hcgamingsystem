<?php

namespace App\Support;

final class PmsReviewStatus
{
    public const PENDING = 1;
    public const APPROVED = 2;
    public const REJECTED = 3;
    public const CANCELLED = 4;

    public const LABELS = [
        self::PENDING => 'Pending approval',
        self::APPROVED => 'Approved',
        self::REJECTED => 'Rejected',
        self::CANCELLED => 'Cancelled',
    ];

    public static function label(int $code): string
    {
        return self::LABELS[$code] ?? 'Unknown';
    }
}
