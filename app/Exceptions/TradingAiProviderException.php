<?php

namespace App\Exceptions;

use RuntimeException;

class TradingAiProviderException extends RuntimeException
{
    public function __construct(public readonly string $failureCode, public readonly ?int $httpStatus = null)
    {
        parent::__construct('The external AI provider is temporarily unavailable.');
    }
}
