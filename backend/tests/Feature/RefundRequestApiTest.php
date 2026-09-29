<?php

namespace Tests\Feature;

use App\Contracts\RefundAiAnalyzer;
use App\Domain\Refunds\RefundAnalysis;
use App\Domain\Refunds\RefundOutcome;
use App\Domain\Refunds\RefundPolicyReasonCode;
use App\Domain\Refunds\RefundReason;
use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\FakeRefundAiAnalyzer;
use Tests\TestCase;

class RefundRequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(RefundAiAnalyzer::class, new FakeRefundAiAnalyzer(new RefundAnalysis(
            classifiedReason: RefundReason::Damaged,
            summary: 'The customer reports a damaged item.',
            suspicious: false,
            conflictingClaims: false,
            confidence: 0.9,
            suggestedResponse: 'We are reviewing the damaged item report.',
        )));
    }

    public function test_valid_request_is_evaluated_and_persisted_from_order_facts(): void
    {
        $this->travelTo('2026-09-26 12:00:00');
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create([
            'total_amount' => '200.00',
            'ordered_at' => now()->subDays(5),
            'final_sale' => false,
        ]);

        $response = $this->postJson('/api/refund-requests', [
            'order_access_token' => app(OrderAccessToken::class)->issue($order),
            'requested_amount' => '120.00',
            'reason' => 'DAMAGED',
            'customer_message' => 'The item arrived damaged.',
            'final_sale' => true,
            'total_amount' => '1.00',
            'ordered_at' => '2026-09-26',
            'policy_outcome' => 'DENIED',
            'suspicious' => true,
            'conflicting_claims' => true,
            'ai_status' => 'UNAVAILABLE',
            'resolution_reason_code' => 'FINAL_SALE',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('outcome', RefundOutcome::Approved->value)
            ->assertJsonPath('reason_code', RefundPolicyReasonCode::EligibleDamagedItem->value);

        $this->assertDatabaseHas('refund_requests', [
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'requested_amount' => '120.00',
            'status' => RefundOutcome::Approved->value,
            'policy_reason_code' => RefundPolicyReasonCode::EligibleDamagedItem->value,
        ]);
    }

    public function test_client_cannot_bypass_persisted_final_sale_or_order_date(): void
    {
        $this->travelTo('2026-09-26 12:00:00');
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create([
            'total_amount' => '300.00',
            'ordered_at' => now()->subDays(31),
            'final_sale' => true,
        ]);

        $response = $this->postJson('/api/refund-requests', [
            'order_access_token' => app(OrderAccessToken::class)->issue($order),
            'requested_amount' => '120.00',
            'reason' => 'DAMAGED',
            'customer_message' => 'The item arrived damaged.',
            'final_sale' => false,
            'ordered_at' => now()->toDateString(),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('outcome', RefundOutcome::Denied->value)
            ->assertJsonPath('reason_code', RefundPolicyReasonCode::FinalSale->value);
    }

    public function test_missing_order_fails_validation(): void
    {
        $response = $this->postJson('/api/refund-requests', [
            'requested_amount' => '20.00',
            'reason' => 'DAMAGED',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('order_access_token');
    }

    public function test_zero_amount_fails_validation(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['total_amount' => '200.00']);

        $response = $this->postJson('/api/refund-requests', [
            'order_access_token' => app(OrderAccessToken::class)->issue($order),
            'requested_amount' => '0.00',
            'reason' => 'DAMAGED',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('requested_amount');
    }

    #[DataProvider('invalidRequestedAmounts')]
    public function test_negative_and_over_precision_amounts_fail_validation(string $amount): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['total_amount' => '200.00']);

        $response = $this->postJson('/api/refund-requests', [
            'order_access_token' => app(OrderAccessToken::class)->issue($order),
            'requested_amount' => $amount,
            'reason' => 'DAMAGED',
            'customer_message' => 'The item arrived damaged.',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('requested_amount');
    }

    public static function invalidRequestedAmounts(): array
    {
        return [
            'negative amount' => ['-10.00'],
            'more than two decimal places' => ['10.001'],
        ];
    }

    public function test_amount_greater_than_order_total_fails_validation(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['total_amount' => '200.00']);

        $response = $this->postJson('/api/refund-requests', [
            'order_access_token' => app(OrderAccessToken::class)->issue($order),
            'requested_amount' => '200.01',
            'reason' => 'DAMAGED',
            'customer_message' => 'The item arrived damaged.',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('requested_amount');
    }

    public function test_amount_outside_supported_money_precision_fails_validation(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['total_amount' => '200.00']);

        $response = $this->postJson('/api/refund-requests', [
            'order_access_token' => app(OrderAccessToken::class)->issue($order),
            'requested_amount' => '9999999999999999999999999999.00',
            'reason' => 'DAMAGED',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('requested_amount');
    }

    public function test_unsupported_reason_fails_validation(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create();

        $response = $this->postJson('/api/refund-requests', [
            'order_access_token' => app(OrderAccessToken::class)->issue($order),
            'requested_amount' => '20.00',
            'reason' => 'UNRECOGNIZED',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_customer_message_over_two_thousand_characters_fails_validation(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create();

        $response = $this->postJson('/api/refund-requests', [
            'order_access_token' => app(OrderAccessToken::class)->issue($order),
            'requested_amount' => '20.00',
            'reason' => 'DAMAGED',
            'customer_message' => str_repeat('a', 2001),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('customer_message');
    }
}
