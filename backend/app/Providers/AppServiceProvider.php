<?php

namespace App\Providers;

use App\Contracts\Ai\LlmClient;
use App\Contracts\RefundAiAnalyzer;
use App\Domain\Refunds\RefundPolicyEngine;
use App\Infrastructure\Ai\LlmRefundAiAnalyzer;
use App\Infrastructure\Ai\Providers\GeminiGenerateContentClient;
use App\Infrastructure\Ai\Providers\OpenAiResponsesClient;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RefundPolicyEngine::class, fn (): RefundPolicyEngine => new RefundPolicyEngine(
            refundWindowDays: (int) config('refunds.refund_window_days'),
            highValueThresholdCents: (int) config('refunds.high_value_threshold_cents'),
        ));

        $this->app->bind(LlmClient::class, function (): LlmClient {
            return match (config('ai.provider')) {
                'openai' => new OpenAiResponsesClient(
                    apiKey: (string) config('ai.openai.api_key'),
                    baseUrl: (string) config('ai.openai.base_url'),
                    model: (string) config('ai.model'),
                    timeoutSeconds: (int) config('ai.timeout_seconds'),
                ),
                'gemini' => new GeminiGenerateContentClient(
                    apiKey: (string) config('ai.gemini.api_key'),
                    baseUrl: (string) config('ai.gemini.base_url'),
                    model: (string) config('ai.model'),
                    timeoutSeconds: (int) config('ai.timeout_seconds'),
                ),
                default => throw new InvalidArgumentException('Unsupported AI provider configured.'),
            };
        });

        $this->app->bind(RefundAiAnalyzer::class, LlmRefundAiAnalyzer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
