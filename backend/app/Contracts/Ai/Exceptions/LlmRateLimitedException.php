<?php

namespace App\Contracts\Ai\Exceptions;

use RuntimeException;

class LlmRateLimitedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The configured AI provider is rate limited.');
    }
}
