<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\RefundRequest;
use Database\Seeders\SyntheticDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PhaseThreeReadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'support.username' => 'test-support',
            'support.password' => 'test-only-support-password',
            'support.auth_ttl_minutes' => 60,
            'support.cookie_name' => 'refund_support_auth',
            'support.cookie_secure' => false,
        ]);
    }

    public function test_order_verification_returns_minimal_customer_facing_facts(): void
    {
        $customer = Customer::factory()->create(['email' => 'private@example.test']);
        $order = Order::factory()->for($customer)->create([
            'order_number' => 'WN-LOOKUP',
            'item_name' => 'Travel Mug',
            'total_amount' => '32.50',
            'ordered_at' => '2026-09-20 10:30:00',
            'final_sale' => false,
        ]);

        $response = $this->postJson('/api/orders/verify', [
            'order_number' => 'WN-LOOKUP',
            'email' => 'private@example.test',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('order.order_number', 'WN-LOOKUP')
            ->assertJsonPath('order.item_name', 'Travel Mug')
            ->assertJsonPath('order.total_amount', '32.50')
            ->assertJsonPath('order.ordered_at', $order->ordered_at->toISOString())
            ->assertJsonPath('order.final_sale', false)
            ->assertJsonMissingPath('order.id')
            ->assertJsonMissing(['email' => 'private@example.test']);
    }

    public function test_unknown_order_number_returns_not_found(): void
    {
        $this->postJson('/api/orders/verify', [
            'order_number' => 'WN-UNKNOWN',
            'email' => 'private@example.test',
        ])->assertNotFound();
    }

    public function test_refund_request_list_is_newest_first_bounded_and_excludes_email(): void
    {
        $this->travelTo('2026-09-26 12:00:00');
        $customer = Customer::factory()->create(['name' => 'List Customer', 'email' => 'private@example.test']);
        $order = Order::factory()->for($customer)->create([
            'order_number' => 'WN-LIST',
            'item_name' => 'Sample Item',
        ]);
        $requests = [];

        for ($index = 0; $index < 52; $index++) {
            $requests[] = $this->createRefundRequest($customer, $order, $index);
        }

        $response = $this->supportGet('/api/refund-requests');

        $response->assertOk()->assertJsonCount(50, 'data');
        $data = $response->json('data');
        $this->assertSame($requests[51]->id, $data[0]['id']);
        $this->assertSame($requests[2]->id, $data[49]['id']);
        $this->assertSame('List Customer', $data[0]['customer']['name']);
        $this->assertSame('WN-LIST', $data[0]['order']['order_number']);
        $this->assertSame('Sample Item', $data[0]['order']['item_name']);
        $this->assertArrayNotHasKey('email', $data[0]['customer']);
        $this->assertArrayNotHasKey('ai_analysis', $data[0]);
    }

    public function test_refund_detail_serializes_seeded_not_analyzed_policy_and_resolution(): void
    {
        $this->travelTo('2026-09-26 12:00:00');
        $this->seed(SyntheticDataSeeder::class);
        $order = Order::query()->where('order_number', 'WN-1008')->firstOrFail();
        $refundRequest = RefundRequest::query()->where('order_id', $order->id)->firstOrFail();

        $response = $this->supportGet('/api/refund-requests/'.$refundRequest->id);

        $response
            ->assertOk()
            ->assertJsonPath('id', $refundRequest->id)
            ->assertJsonPath('customer.name', 'Jamie Hall')
            ->assertJsonPath('order.order_number', 'WN-1008')
            ->assertJsonPath('customer_reason_hint', 'DAMAGED')
            ->assertJsonPath('ai.status', 'NOT_ANALYZED')
            ->assertJsonPath('ai.analysis', null)
            ->assertJsonPath('ai.provider', null)
            ->assertJsonPath('ai.model', null)
            ->assertJsonPath('ai.error_code', null)
            ->assertJsonPath('policy.outcome', 'DENIED')
            ->assertJsonPath('policy.reason_code', 'FINAL_SALE')
            ->assertJsonPath('resolution.outcome', 'DENIED')
            ->assertJsonPath('resolution.reason_code', 'FINAL_SALE')
            ->assertJsonMissingPath('customer.email');
    }

    public function test_unknown_refund_request_returns_not_found(): void
    {
        $this->supportGet('/api/refund-requests/999999')->assertNotFound();
    }

    private function supportGet(string $uri): TestResponse
    {
        $login = $this->postJson('/api/support/login', [
            'username' => 'test-support',
            'password' => 'test-only-support-password',
        ])->assertOk();

        $cookie = $login->getCookie('refund_support_auth', false)->getValue();

        return $this->withCredentials()
            ->withUnencryptedCookie('refund_support_auth', $cookie)
            ->getJson($uri);
    }

    private function createRefundRequest(Customer $customer, Order $order, int $index): RefundRequest
    {
        $createdAt = now()->addSeconds($index);

        return RefundRequest::query()->forceCreate([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'requested_amount' => '10.00',
            'reason' => 'OTHER',
            'customer_message' => 'Seeded read-list test request.',
            'status' => 'DENIED',
            'policy_reason_code' => 'UNSUPPORTED_REASON',
            'policy_explanation' => 'The request reason is unsupported.',
            'ai_status' => 'NOT_ANALYZED',
            'policy_outcome' => 'DENIED',
            'resolution_reason_code' => 'UNSUPPORTED_REASON',
            'resolution_explanation' => 'The request reason is unsupported.',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
