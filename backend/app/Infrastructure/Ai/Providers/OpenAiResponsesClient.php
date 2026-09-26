<?php

namespace App\Infrastructure\Ai\Providers;

use App\Contracts\Ai\Exceptions\LlmInvalidResponseException;
use App\Contracts\Ai\Exceptions\LlmRateLimitedException;
use App\Contracts\Ai\Exceptions\LlmUnavailableException;
use App\Contracts\Ai\JsonSchema;
use App\Contracts\Ai\LlmClient;
use App\Contracts\Ai\LlmRequest;
use App\Contracts\Ai\LlmResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenAiResponsesClient implements LlmClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeoutSeconds,
    ) {}

    public function generateStructured(LlmRequest $request, JsonSchema $schema): LlmResponse
    {
        if (trim($this->apiKey) === '') {
            throw new LlmUnavailableException;
        }

        try {
            $response = Http::baseUrl(rtrim($this->baseUrl, '/'))
                ->acceptJson()
                ->withToken($this->apiKey)
                ->timeout($this->timeoutSeconds)
                ->post('/responses', [
                    'model' => $this->model,
                    'input' => [
                        ['role' => 'system', 'content' => $request->systemPrompt],
                        [
                            'role' => 'user',
                            'content' => json_encode($request->userPromptContext, JSON_THROW_ON_ERROR),
                        ],
                    ],
                    'max_output_tokens' => 500,
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => $schema->name,
                            'schema' => $schema->schema,
                            'strict' => true,
                        ],
                    ],
                ]);
        } catch (ConnectionException|\JsonException) {
            throw new LlmUnavailableException;
        }

        if ($response->status() === 429) {
            throw new LlmRateLimitedException;
        }

        if (! $response->successful()) {
            throw new LlmUnavailableException;
        }

        return new LlmResponse(
            text: $this->extractStructuredText($response->json()),
            provider: 'openai',
            model: $this->model,
        );
    }

    private function extractStructuredText(mixed $body): string
    {
        if (! is_array($body) || ($body['status'] ?? null) !== 'completed') {
            throw new LlmInvalidResponseException;
        }

        if (isset($body['output_text']) && is_string($body['output_text'])) {
            return $body['output_text'];
        }

        foreach ($body['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new LlmInvalidResponseException;
                }

                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        throw new LlmInvalidResponseException;
    }
}
