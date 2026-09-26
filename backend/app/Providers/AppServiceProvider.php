<?php

namespace App\Providers;

use App\Contracts\Ai\LlmClient;
use App\Contracts\RefundAiAnalyzer;
use App\Domain\Refunds\RefundPolicyEngine;
use App\Infrastructure\Ai\LlmRefundAiAnalyzer;
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
            if (config('ai.provider') !== 'openai') {
                throw new InvalidArgumentException('Unsupported AI provider configured.');
            }

            return new OpenAiResponsesClient(
                apiKey: (string) config('ai.openai.api_key'),
                baseUrl: (string) config('ai.openai.base_url'),
                model: (string) config('ai.model'),
                timeoutSeconds: (int) config('ai.timeout_seconds'),
            );
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
