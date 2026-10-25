<?php

namespace App\Services\MarketInsight;

use RuntimeException;

final class MarketDataException extends RuntimeException
{
    public function __construct(public readonly string $dataStatus, string $safeMessage)
    {
        parent::__construct($safeMessage);
    }
}
