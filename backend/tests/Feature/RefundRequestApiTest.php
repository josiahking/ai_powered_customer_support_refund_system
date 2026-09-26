<?php

namespace Tests\Feature;

use App\Domain\Refunds\RefundOutcome;
use App\Domain\Refunds\RefundPolicyReasonCode;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundRequestApiTest extends TestCase
{
    use RefreshDatabase;

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
            'order_id' => $order->id,
            'requested_amount' => '120.00',
            'reason' => 'DAMAGED',
            'customer_message' => 'The item arrived damaged.',
            'final_sale' => true,
            'total_amount' => '1.00',
            'ordered_at' => '2026-09-26',
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
            'order_id' => $order->id,
            'requested_amount' => '120.00',
            'reason' => 'DAMAGED',
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
            'order_id' => 999999,
            'requested_amount' => '20.00',
            'reason' => 'DAMAGED',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('order_id');
    }

    public function test_zero_amount_fails_validation(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['total_amount' => '200.00']);

        $response = $this->postJson('/api/refund-requests', [
            'order_id' => $order->id,
            'requested_amount' => '0.00',
            'reason' => 'DAMAGED',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('requested_amount');
    }

    public function test_amount_greater_than_order_total_fails_validation(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['total_amount' => '200.00']);

        $response = $this->postJson('/api/refund-requests', [
            'order_id' => $order->id,
            'requested_amount' => '200.01',
            'reason' => 'DAMAGED',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('requested_amount');
    }

    public function test_amount_outside_supported_money_precision_fails_validation(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['total_amount' => '200.00']);

        $response = $this->postJson('/api/refund-requests', [
            'order_id' => $order->id,
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
            'order_id' => $order->id,
            'requested_amount' => '20.00',
            'reason' => 'UNRECOGNIZED',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('reason');
    }
}
