<?php

namespace Tests\Feature;

use App\Contracts\Ai\LlmClient;
use App\Contracts\RefundAiAnalyzer;
use App\Infrastructure\Ai\LlmRefundAiAnalyzer;
use App\Infrastructure\Ai\Providers\OpenAiResponsesClient;
use InvalidArgumentException;
use Tests\TestCase;

class AiProviderBindingTest extends TestCase
{
    public function test_openai_provider_is_resolved_from_central_configuration(): void
    {
        config([
            'ai.provider' => 'openai',
            'ai.model' => 'test-model',
            'ai.timeout_seconds' => 5,
            'ai.openai.api_key' => '',
            'ai.openai.base_url' => 'https://api.openai.com/v1',
        ]);

        $this->assertInstanceOf(OpenAiResponsesClient::class, $this->app->make(LlmClient::class));
        $this->assertInstanceOf(LlmRefundAiAnalyzer::class, $this->app->make(RefundAiAnalyzer::class));
    }

    public function test_unknown_provider_fails_with_clear_configuration_error(): void
    {
        config(['ai.provider' => 'not-configured']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported AI provider');

        $this->app->make(LlmClient::class);
    }
}
