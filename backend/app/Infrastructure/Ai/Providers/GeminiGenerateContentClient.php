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
use Illuminate\Support\Sleep;
use JsonException;

class GeminiGenerateContentClient implements LlmClient
{
    private const MAX_ATTEMPTS = 3;

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

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = Http::baseUrl(rtrim($this->baseUrl, '/'))
                    ->acceptJson()
                    ->withHeaders(['x-goog-api-key' => $this->apiKey])
                    ->timeout($this->timeoutSeconds)
                    ->post('/models/'.rawurlencode($this->model).':generateContent', [
                        'systemInstruction' => [
                            'parts' => [['text' => $request->systemPrompt]],
                        ],
                        'contents' => [[
                            'role' => 'user',
                            'parts' => [[
                                'text' => json_encode($request->userPromptContext, JSON_THROW_ON_ERROR),
                            ]],
                        ]],
                        'generationConfig' => [
                            'responseFormat' => [
                                'text' => [
                                    'mimeType' => 'APPLICATION_JSON',
                                    'schema' => $schema->schema,
                                ],
                            ],
                        ],
                    ]);
            } catch (ConnectionException|JsonException) {
                throw new LlmUnavailableException;
            }

            if ($response->status() !== 503 || $attempt === self::MAX_ATTEMPTS) {
                break;
            }

            Sleep::sleep($attempt);
        }

        if ($response->status() === 429) {
            throw new LlmRateLimitedException;
        }

        if (! $response->successful()) {
            throw new LlmUnavailableException;
        }

        return new LlmResponse(
            text: $this->extractStructuredText($response->json()),
            provider: 'gemini',
            model: $this->model,
        );
    }

    private function extractStructuredText(mixed $body): string
    {
        if (! is_array($body) || ! is_array($body['candidates'] ?? null)) {
            throw new LlmInvalidResponseException;
        }

        foreach ($body['candidates'] as $candidate) {
            $parts = is_array($candidate) ? ($candidate['content']['parts'] ?? null) : null;

            if (! is_array($parts)) {
                continue;
            }

            foreach ($parts as $part) {
                $text = is_array($part) ? ($part['text'] ?? null) : null;

                if (is_string($text) && trim($text) !== '') {
                    return $text;
                }
            }
        }

        throw new LlmInvalidResponseException;
    }
}
