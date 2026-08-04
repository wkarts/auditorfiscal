<?php

namespace App\Services;

use RuntimeException;

class AnalysisResultValidationException extends RuntimeException
{
    public function __construct(public readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }
}
