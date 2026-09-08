<?php

namespace App\Services;

use RuntimeException;

class FraudCheckerApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $providerBlocked = false,
        public readonly ?int $statusCode = null,
    ) {
        parent::__construct($message, $statusCode ?? 0);
    }
}
