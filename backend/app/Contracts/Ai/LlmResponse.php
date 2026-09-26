<?php

namespace App\Contracts\Ai;

readonly class LlmResponse
{
    public function __construct(
        public string $text,
        public string $provider = 'unknown',
        public string $model = 'unknown',
    ) {}
}
