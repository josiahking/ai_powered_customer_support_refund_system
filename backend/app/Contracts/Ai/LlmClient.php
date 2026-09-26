<?php

namespace App\Contracts\Ai;

interface LlmClient
{
    public function generateStructured(LlmRequest $request, JsonSchema $schema): LlmResponse;
}
