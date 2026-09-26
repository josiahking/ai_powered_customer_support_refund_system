<?php

namespace App\Contracts\Ai;

readonly class JsonSchema
{
    /** @param array<string, mixed> $schema */
    public function __construct(
        public string $name,
        public array $schema,
    ) {}
}
