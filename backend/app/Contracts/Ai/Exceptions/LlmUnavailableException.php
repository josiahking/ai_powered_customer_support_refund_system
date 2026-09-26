<?php

namespace App\Contracts\Ai\Exceptions;

use RuntimeException;

class LlmUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The configured AI provider is unavailable.');
    }
}
