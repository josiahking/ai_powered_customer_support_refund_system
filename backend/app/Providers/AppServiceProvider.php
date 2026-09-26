<?php

namespace App\Providers;

use App\Domain\Refunds\RefundPolicyEngine;
use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
