<?php

namespace App\Exceptions;

use RuntimeException;

class PmsBusinessRuleException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $error = 'WORKFLOW_STAGE_RESTRICTION',
        public readonly array $details = [],
        public readonly int $status = 409,
    ) {
        parent::__construct($message);
    }
}
