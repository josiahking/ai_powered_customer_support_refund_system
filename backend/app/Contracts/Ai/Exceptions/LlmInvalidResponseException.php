<?php

namespace App\Contracts\Ai\Exceptions;

use RuntimeException;

class LlmInvalidResponseException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The configured AI provider returned an invalid analysis response.');
    }
}
