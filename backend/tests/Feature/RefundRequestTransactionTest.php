<?php

namespace Tests\Feature;

use App\Contracts\RefundAiAnalyzer;
use App\Domain\Refunds\RefundAnalysis;
use App\Domain\Refunds\RefundReason;
use App\Models\Customer;
use App\Models\Order;
use App\Services\RefundRequestService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Fakes\FakeRefundAiAnalyzer;
use Tests\TestCase;

class RefundRequestTransactionTest extends TestCase
{
    use DatabaseMigrations;

    public function test_external_analysis_runs_outside_a_database_transaction_and_request_persists(): void
    {
        $analyzer = new FakeRefundAiAnalyzer(new RefundAnalysis(
            classifiedReason: RefundReason::Damaged,
            summary: 'The customer reports a cracked item.',
            suspicious: false,
            conflictingClaims: false,
            confidence: 0.9,
            suggestedResponse: 'We are reviewing the damaged item report.',
        ));
        $this->app->instance(RefundAiAnalyzer::class, $analyzer);

        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create([
            'ordered_at' => now()->subDays(5),
            'total_amount' => '200.00',
            'final_sale' => false,
        ]);

        $request = app(RefundRequestService::class)->create(
            orderId: $order->id,
            requestedAmount: '40.00',
            reason: RefundReason::Damaged,
            customerMessage: 'The item arrived cracked.',
        );

        $this->assertSame([0], $analyzer->transactionLevels);
        $this->assertDatabaseHas('refund_requests', [
            'id' => $request->id,
            'order_id' => $order->id,
            'status' => 'APPROVED',
        ]);
    }
}
