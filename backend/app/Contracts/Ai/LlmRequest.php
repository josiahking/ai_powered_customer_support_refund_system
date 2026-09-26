<?php

namespace App\Contracts\Ai;

readonly class LlmRequest
{
    /** @param array<string, string> $userPromptContext */
    public function __construct(
        public string $systemPrompt,
        public array $userPromptContext,
    ) {}
}
