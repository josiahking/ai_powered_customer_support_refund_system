<?php

namespace Tests\Feature;

use App\Contracts\RefundAiAnalyzer;
use App\Domain\Refunds\RefundAnalysis;
use App\Domain\Refunds\RefundReason;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\Fakes\FakeRefundAiAnalyzer;
use Tests\TestCase;

class CustomerOrderOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(RefundAiAnalyzer::class, new FakeRefundAiAnalyzer(new RefundAnalysis(
            classifiedReason: RefundReason::Damaged,
            summary: 'Damaged item.',
            suspicious: false,
            conflictingClaims: false,
            confidence: 0.9,
            suggestedResponse: 'We are reviewing this.',
        )));
        config(['order_access.ttl_minutes' => 15]);
    }

    public function test_order_number_only_lookup_is_not_publicly_available(): void
    {
        $customer = Customer::factory()->create(['name' => 'Private Customer']);
        Order::factory()->for($customer)->create(['order_number' => 'WN-1001']);

        $this->getJson('/api/orders/WN-1001')
            ->assertNotFound()
            ->assertJsonMissing(['name' => 'Private Customer']);
    }

    public function test_correct_order_and_email_returns_safe_details_and_access_token(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Private Customer',
            'email' => 'avery.bennett@example.test',
        ]);
        $order = Order::factory()->for($customer)->create([
            'order_number' => 'WN-1001',
            'item_name' => 'Wireless Speaker',
            'total_amount' => '89.90',
        ]);

        $response = $this->postJson('/api/orders/verify', [
            'order_number' => ' wn-1001 ',
            'email' => ' AVERY.BENNETT@example.test ',
        ]);

        $response->assertOk()
            ->assertJsonPath('order.order_number', 'WN-1001')
            ->assertJsonPath('order.item_name', 'Wireless Speaker')
            ->assertJsonPath('expires_in_minutes', 15)
            ->assertJsonMissingPath('order.id')
            ->assertJsonMissingPath('customer_id')
            ->assertJsonMissingPath('email')
            ->assertJsonMissingPath('name');
        $this->assertIsString($response->json('order_access_token'));
        $claims = json_decode(Crypt::decryptString($response->json('order_access_token')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['purpose', 'order_id', 'customer_id', 'expires_at'], array_keys($claims));
        $this->assertSame('refund-order-access', $claims['purpose']);
        $this->assertSame($order->id, $claims['order_id']);
        $this->assertSame($customer->id, $claims['customer_id']);
        $this->assertGreaterThan(now()->timestamp, $claims['expires_at']);
    }

    public function test_wrong_email_and_unknown_order_return_identical_generic_responses(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Private Customer',
            'email' => 'avery.bennett@example.test',
        ]);
        Order::factory()->for($customer)->create(['order_number' => 'WN-1001']);
        $payload = ['email' => 'wrong.customer@example.test'];

        $wrongEmail = $this->postJson('/api/orders/verify', $payload + ['order_number' => 'WN-1001']);
        $unknownOrder = $this->postJson('/api/orders/verify', $payload + ['order_number' => 'WN-9999']);

        $message = 'We could not verify that order. Check the order number and email and try again.';
        $wrongEmail->assertNotFound()->assertExactJson(['message' => $message]);
        $unknownOrder->assertNotFound()->assertExactJson(['message' => $message]);
        $this->assertStringNotContainsString('Private Customer', $wrongEmail->getContent());
        $this->assertStringNotContainsString('avery.bennett@example.test', $unknownOrder->getContent());
    }

    public function test_verification_input_is_validated(): void
    {
        $this->postJson('/api/orders/verify', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['order_number', 'email']);
        $this->postJson('/api/orders/verify', [
            'order_number' => 'WN-1001',
            'email' => 'not-an-email',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_verification_route_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/orders/verify', [
                'order_number' => 'WN-UNKNOWN',
                'email' => 'wrong.customer@example.test',
            ])->assertNotFound();
        }

        $this->postJson('/api/orders/verify', [
            'order_number' => 'WN-UNKNOWN',
            'email' => 'wrong.customer@example.test',
        ])->assertTooManyRequests();
    }

    public function test_valid_verification_token_persists_refund_to_verified_order_customer(): void
    {
        $customer = Customer::factory()->create(['email' => 'avery.bennett@example.test']);
        $order = Order::factory()->for($customer)->create(['order_number' => 'WN-1001']);
        $token = $this->verify('WN-1001', 'avery.bennett@example.test');

        $response = $this->postJson('/api/refund-requests', [
            'order_access_token' => $token,
            'requested_amount' => '20.00',
            'reason' => 'DAMAGED',
            'customer_message' => 'The speaker arrived damaged.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('refund_requests', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_browser_supplied_order_id_is_rejected_even_with_valid_token(): void
    {
        $first = Customer::factory()->create(['email' => 'avery.bennett@example.test']);
        Order::factory()->for($first)->create(['order_number' => 'WN-1001']);
        $other = Customer::factory()->create();
        $otherOrder = Order::factory()->for($other)->create(['order_number' => 'WN-1002']);
        $token = $this->verify('WN-1001', 'avery.bennett@example.test');

        $this->postJson('/api/refund-requests', [
            'order_access_token' => $token,
            'order_id' => $otherOrder->id,
            'customer_id' => $other->id,
            'requested_amount' => '20.00',
            'reason' => 'DAMAGED',
            'customer_message' => 'The speaker arrived damaged.',
        ])->assertUnprocessable()->assertJsonValidationErrors(['order_id', 'customer_id']);
        $this->assertDatabaseCount('refund_requests', 0);
    }

    public function test_malformed_tampered_expired_and_wrong_purpose_tokens_are_rejected(): void
    {
        $customer = Customer::factory()->create(['email' => 'avery.bennett@example.test']);
        $order = Order::factory()->for($customer)->create(['order_number' => 'WN-1001']);
        $valid = $this->verify('WN-1001', 'avery.bennett@example.test');
        $expired = Crypt::encryptString(json_encode([
            'purpose' => 'refund-order-access',
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'expires_at' => now()->subSecond()->timestamp,
        ], JSON_THROW_ON_ERROR));
        $wrongPurpose = Crypt::encryptString(json_encode([
            'purpose' => 'support-auth',
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'expires_at' => now()->addMinute()->timestamp,
        ], JSON_THROW_ON_ERROR));
        $invalidShape = Crypt::encryptString(json_encode([
            'purpose' => 'refund-order-access',
            'order_id' => (string) $order->id,
            'customer_id' => $customer->id,
            'expires_at' => now()->addMinute()->timestamp,
            'email' => 'avery.bennett@example.test',
        ], JSON_THROW_ON_ERROR));

        foreach (['malformed-token', $valid.'tampered', $expired, $wrongPurpose, $invalidShape] as $token) {
            $this->postJson('/api/refund-requests', [
                'order_access_token' => $token,
                'requested_amount' => '20.00',
                'reason' => 'DAMAGED',
                'customer_message' => 'The speaker arrived damaged.',
            ])->assertForbidden()->assertExactJson([
                'message' => 'Order verification is invalid or has expired. Verify your order again.',
            ]);
        }
    }

    public function test_token_claims_must_match_the_loaded_order_customer_relationship(): void
    {
        $customer = Customer::factory()->create(['email' => 'avery.bennett@example.test']);
        $order = Order::factory()->for($customer)->create(['order_number' => 'WN-1001']);
        $otherCustomer = Customer::factory()->create();
        $token = Crypt::encryptString(json_encode([
            'purpose' => 'refund-order-access',
            'order_id' => $order->id,
            'customer_id' => $otherCustomer->id,
            'expires_at' => now()->addMinute()->timestamp,
        ], JSON_THROW_ON_ERROR));

        $this->postJson('/api/refund-requests', [
            'order_access_token' => $token,
            'requested_amount' => '20.00',
            'reason' => 'DAMAGED',
            'customer_message' => 'The speaker arrived damaged.',
        ])->assertForbidden();
        $this->assertDatabaseCount('refund_requests', 0);
    }

    private function verify(string $orderNumber, string $email): string
    {
        return $this->postJson('/api/orders/verify', [
            'order_number' => $orderNumber,
            'email' => $email,
        ])->assertOk()->json('order_access_token');
    }
}
