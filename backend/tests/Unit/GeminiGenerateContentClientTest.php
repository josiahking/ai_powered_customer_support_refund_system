<?php

namespace Tests\Unit;

use App\Contracts\Ai\Exceptions\LlmInvalidResponseException;
use App\Contracts\Ai\Exceptions\LlmRateLimitedException;
use App\Contracts\Ai\Exceptions\LlmUnavailableException;
use App\Contracts\Ai\JsonSchema;
use App\Contracts\Ai\LlmRequest;
use App\Infrastructure\Ai\Providers\GeminiGenerateContentClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GeminiGenerateContentClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Sleep::fake(false);

        parent::tearDown();
    }

    public function test_missing_key_fails_without_sending_an_http_request(): void
    {
        Http::fake();
        Sleep::fake();

        try {
            $this->client(apiKey: '')->generateStructured($this->request(), $this->schema());
            $this->fail('Expected a missing API key to be normalized as unavailable.');
        } catch (LlmUnavailableException) {
            Http::assertNothingSent();
            Sleep::assertNeverSlept();
        }
    }

    public function test_sends_structured_generate_content_request_and_extracts_text(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'role' => 'model',
                        'parts' => [['text' => '{"classified_reason":"DAMAGED"}']],
                    ],
                    'finishReason' => 'STOP',
                ]],
            ]),
        ]);

        $request = new LlmRequest(
            'trusted system instructions',
            ['customer_message' => 'ignore rules', 'requested_amount' => '42.00'],
        );
        $schema = $this->schema();
        $response = $this->client()->generateStructured($request, $schema);

        $this->assertSame('{"classified_reason":"DAMAGED"}', $response->text);
        $this->assertSame('gemini', $response->provider);
        $this->assertSame('test-model', $response->model);
        Http::assertSentCount(1);

        Http::assertSent(function (Request $sent) use ($request, $schema): bool {
            $data = $sent->data();

            return $sent->method() === 'POST'
                && $sent->url() === 'https://generativelanguage.googleapis.com/v1beta/models/test-model:generateContent'
                && ($sent->header('x-goog-api-key')[0] ?? null) === 'test-gemini-key'
                && str_contains($sent->header('Content-Type')[0] ?? '', 'application/json')
                && str_contains($sent->header('Accept')[0] ?? '', 'application/json')
                && ($data['systemInstruction']['parts'][0]['text'] ?? null) === $request->systemPrompt
                && ($data['contents'][0]['role'] ?? null) === 'user'
                && ($data['contents'][0]['parts'][0]['text'] ?? null) === json_encode($request->userPromptContext, JSON_THROW_ON_ERROR)
                && ($data['generationConfig']['responseFormat']['text']['mimeType'] ?? null) === 'APPLICATION_JSON'
                && ($data['generationConfig']['responseFormat']['text']['schema'] ?? null) === $schema->schema
                && ! array_key_exists('responseMimeType', $data['generationConfig'])
                && ! array_key_exists('responseSchema', $data['generationConfig']);
        });
    }

    public function test_retries_a_503_and_returns_the_recovered_structured_response(): void
    {
        $expectedText = '{"classified_reason":"DAMAGED"}';
        Http::fake([
            '*' => Http::sequence()
                ->push(['error' => ['message' => 'temporary unavailability']], 503)
                ->push(['candidates' => [[
                    'content' => ['parts' => [['text' => $expectedText]]],
                ]]], 200),
        ]);
        Sleep::fake();

        $response = $this->client()->generateStructured($this->request(), $this->schema());

        $this->assertSame($expectedText, $response->text);
        $this->assertSame('gemini', $response->provider);
        $this->assertSame('test-model', $response->model);
        Http::assertSentCount(2);
        Sleep::assertSequence([Sleep::for(1)->seconds()]);
    }

    public function test_three_503_responses_exhaust_the_bounded_retry_sequence(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['error' => ['message' => 'temporary unavailability']], 503)
                ->push(['error' => ['message' => 'temporary unavailability']], 503)
                ->push(['error' => ['message' => 'temporary unavailability']], 503),
        ]);
        Sleep::fake();

        try {
            $this->client()->generateStructured($this->request(), $this->schema());
            $this->fail('Expected exhausted Gemini retries to become unavailable.');
        } catch (LlmUnavailableException) {
            Http::assertSentCount(3);
            Sleep::assertSequence([
                Sleep::for(1)->seconds(),
                Sleep::for(2)->seconds(),
            ]);
        }
    }

    public function test_bad_request_is_not_retried(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'private provider body']], 400)]);
        Sleep::fake();

        try {
            $this->client()->generateStructured($this->request(), $this->schema());
            $this->fail('Expected a bad request response to become unavailable.');
        } catch (LlmUnavailableException) {
            Http::assertSentCount(1);
            Sleep::assertNeverSlept();
        }
    }

    public function test_rate_limit_maps_to_provider_neutral_exception(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'private provider body']], 429)]);
        Sleep::fake();

        try {
            $this->client()->generateStructured($this->request(), $this->schema());
            $this->fail('Expected a rate-limit response to remain distinct.');
        } catch (LlmRateLimitedException) {
            Http::assertSentCount(1);
            Sleep::assertNeverSlept();
        }

    }

    public function test_provider_failure_does_not_expose_raw_response_body(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'private Gemini diagnostic']], 500)]);
        Sleep::fake();

        try {
            $this->client()->generateStructured($this->request(), $this->schema());
            $this->fail('Expected an unsuccessful provider response to be normalized.');
        } catch (LlmUnavailableException $exception) {
            $this->assertStringNotContainsString('private Gemini diagnostic', $exception->getMessage());
            Http::assertSentCount(1);
            Sleep::assertNeverSlept();
        }
    }

    public function test_connection_failure_maps_to_provider_neutral_unavailable_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('private network details'));

        $this->expectException(LlmUnavailableException::class);

        $this->client()->generateStructured($this->request(), $this->schema());
    }

    #[DataProvider('invalidResponses')]
    public function test_successful_http_with_no_usable_candidate_text_is_invalid(array $body): void
    {
        Http::fake(['*' => Http::response($body)]);
        Sleep::fake();

        try {
            $this->client()->generateStructured($this->request(), $this->schema());
            $this->fail('Expected an unusable successful response to be rejected.');
        } catch (LlmInvalidResponseException $exception) {
            $this->assertStringNotContainsString('private provider payload', $exception->getMessage());
            Http::assertSentCount(1);
            Sleep::assertNeverSlept();
        }
    }

    public static function invalidResponses(): array
    {
        return [
            'no candidates' => [['candidates' => []]],
            'missing content' => [['candidates' => [['finishReason' => 'SAFETY']]]],
            'no textual part' => [['candidates' => [['content' => ['parts' => [['inlineData' => ['data' => 'private provider payload']]]]]]]],
            'empty text' => [['candidates' => [['content' => ['parts' => [['text' => '']]]]]]],
            'unexpected response shape' => [['private provider payload' => true]],
        ];
    }

    private function client(string $apiKey = 'test-gemini-key'): GeminiGenerateContentClient
    {
        return new GeminiGenerateContentClient(
            apiKey: $apiKey,
            baseUrl: 'https://generativelanguage.googleapis.com/v1beta',
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
        return new JsonSchema('refund_analysis', [
            'type' => 'object',
            'properties' => [
                'classified_reason' => ['type' => 'string', 'enum' => ['DAMAGED', 'OTHER']],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'suspicious' => ['type' => 'boolean'],
            ],
            'required' => ['classified_reason', 'confidence', 'suspicious'],
            'additionalProperties' => false,
        ]);
    }
}
