<?php

namespace Tests\Fakes;

use App\Contracts\Ai\JsonSchema;
use App\Contracts\Ai\LlmClient;
use App\Contracts\Ai\LlmRequest;
use App\Contracts\Ai\LlmResponse;

class FakeLlmClient implements LlmClient
{
    public ?LlmRequest $request = null;

    public ?JsonSchema $schema = null;

    public function __construct(private LlmResponse $response) {}

    public function generateStructured(LlmRequest $request, JsonSchema $schema): LlmResponse
    {
        $this->request = $request;
        $this->schema = $schema;

        return $this->response;
    }
}
