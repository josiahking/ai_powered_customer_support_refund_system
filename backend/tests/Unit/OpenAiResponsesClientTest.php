<?php

namespace Tests\Unit;

use App\Contracts\Ai\Exceptions\LlmInvalidResponseException;
use App\Contracts\Ai\Exceptions\LlmRateLimitedException;
use App\Contracts\Ai\Exceptions\LlmUnavailableException;
use App\Contracts\Ai\JsonSchema;
use App\Contracts\Ai\LlmRequest;
use App\Infrastructure\Ai\Providers\OpenAiResponsesClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiResponsesClientTest extends TestCase
{
    public function test_sends_configured_model_bearer_auth_and_strict_schema(): void
    {
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'status' => 'completed',
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => '{"ok":true}']],
                ]],
            ]),
        ]);

        $response = $this->client()->generateStructured(
            new LlmRequest('system prompt', ['customer_message' => 'message']),
            new JsonSchema('refund_analysis', ['type' => 'object', 'properties' => [], 'required' => [], 'additionalProperties' => false]),
        );

        $this->assertSame('{"ok":true}', $response->text);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses'
            && ($request->header('Authorization')[0] ?? null) === 'Bearer test-key-not-a-real-secret'
            && $request['model'] === 'test-model'
            && $request['text']['format']['type'] === 'json_schema'
            && $request['text']['format']['strict'] === true
            && $request['text']['format']['name'] === 'refund_analysis'
        );
    }

    public function test_rate_limit_maps_to_provider_neutral_exception(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'private provider body']], 429)]);

        $this->expectException(LlmRateLimitedException::class);

        $this->client()->generateStructured($this->request(), $this->schema());
    }

    public function test_server_failure_maps_without_exposing_provider_body(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'private provider body']], 503)]);

        try {
            $this->client()->generateStructured($this->request(), $this->schema());
            $this->fail('Expected provider failure to be normalized.');
        } catch (LlmUnavailableException $exception) {
            $this->assertStringNotContainsString('private provider body', $exception->getMessage());
        }
    }

    public function test_connection_failure_maps_to_provider_neutral_unavailable_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('network timeout details'));

        $this->expectException(LlmUnavailableException::class);

        $this->client()->generateStructured($this->request(), $this->schema());
    }

    public function test_missing_structured_output_maps_to_invalid_response(): void
    {
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => []])]);

        $this->expectException(LlmInvalidResponseException::class);

        $this->client()->generateStructured($this->request(), $this->schema());
    }

    private function client(): OpenAiResponsesClient
    {
        return new OpenAiResponsesClient(
            apiKey: 'test-key-not-a-real-secret',
            baseUrl: 'https://api.openai.com/v1',
            model: 'test-model',
            timeoutSeconds: 5,
        );
    }

    private function request(): LlmRequest
    {
        return new LlmRequest('system prompt', ['customer_message' => 'message']);
    }

    private function schema(): JsonSchema
    {
        return new JsonSchema('refund_analysis', ['type' => 'object', 'properties' => [], 'required' => [], 'additionalProperties' => false]);
    }
}
