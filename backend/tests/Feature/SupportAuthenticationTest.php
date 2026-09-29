<?php

namespace Tests\Feature;

use App\Contracts\RefundAiAnalyzer;
use App\Domain\Refunds\RefundAnalysis;
use App\Domain\Refunds\RefundReason;
use App\Models\Customer;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Services\OrderAccessToken;
use Database\Seeders\SyntheticDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\Fakes\FakeRefundAiAnalyzer;
use Tests\TestCase;

class SupportAuthenticationTest extends TestCase
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
            'support.frontend_url' => 'http://localhost:3000',
        ]);

        $this->app->instance(RefundAiAnalyzer::class, new FakeRefundAiAnalyzer(new RefundAnalysis(
            classifiedReason: RefundReason::Damaged,
            summary: 'The item arrived damaged.',
            suspicious: false,
            conflictingClaims: false,
            confidence: 0.9,
            suggestedResponse: 'We are reviewing the damage report.',
        )));
    }

    public function test_unauthenticated_support_list_returns_unauthorized(): void
    {
        $this->getJson('/api/refund-requests')->assertUnauthorized();
    }

    public function test_unauthenticated_support_detail_returns_unauthorized(): void
    {
        $this->seed(SyntheticDataSeeder::class);
        $requestId = RefundRequest::query()->value('id');

        $this->getJson('/api/refund-requests/'.$requestId)->assertUnauthorized();
    }

    public function test_customer_order_lookup_remains_public(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create(['order_number' => 'WN-PUBLIC']);

        $this->getJson('/api/orders/'.$order->order_number)->assertNotFound();
    }

    public function test_customer_refund_submission_remains_public(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->for($customer)->create([
            'total_amount' => '100.00',
            'ordered_at' => now()->subDays(2),
            'final_sale' => false,
        ]);

        $this->postJson('/api/refund-requests', [
            'order_access_token' => app(OrderAccessToken::class)->issue($order),
            'requested_amount' => '25.00',
            'reason' => 'DAMAGED',
            'customer_message' => 'The item arrived damaged.',
        ])->assertCreated();
    }

    public function test_valid_login_issues_an_opaque_securely_scoped_cookie(): void
    {
        $before = now()->timestamp;
        $response = $this->postJson('/api/support/login', [
            'username' => 'test-support',
            'password' => 'test-only-support-password',
        ]);

        $response->assertOk()->assertExactJson(['authenticated' => true]);
        $cookie = $response->getCookie('refund_support_auth', false);
        $this->assertNotNull($cookie);
        $this->assertSame('/', $cookie->getPath());
        $this->assertFalse($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertGreaterThanOrEqual($before + 3598, $cookie->getExpiresTime());
        $this->assertLessThanOrEqual($before + 3602, $cookie->getExpiresTime());

        $payload = json_decode(Crypt::decryptString($cookie->getValue()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['purpose', 'subject', 'expires_at'], array_keys($payload));
        $this->assertSame('support-auth', $payload['purpose']);
        $this->assertSame('test-support', $payload['subject']);
    }

    public function test_secure_cookie_flag_follows_configuration(): void
    {
        config(['support.cookie_secure' => true]);

        $cookie = $this->postJson('/api/support/login', [
            'username' => 'test-support',
            'password' => 'test-only-support-password',
        ])->assertOk()->getCookie('refund_support_auth', false);

        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function test_wrong_username_and_password_return_generic_unauthorized(): void
    {
        foreach ([
            ['username' => 'other', 'password' => 'test-only-support-password'],
            ['username' => 'test-support', 'password' => 'wrong'],
        ] as $credentials) {
            $this->postJson('/api/support/login', $credentials)
                ->assertUnauthorized()
                ->assertExactJson(['message' => 'Invalid support credentials.']);
        }
    }

    public function test_missing_login_fields_fail_validation(): void
    {
        $this->postJson('/api/support/login', [])->assertUnprocessable()->assertJsonValidationErrors([
            'username',
            'password',
        ]);
    }

    public function test_empty_configured_password_fails_closed(): void
    {
        config(['support.password' => '']);

        $this->postJson('/api/support/login', [
            'username' => 'test-support',
            'password' => 'any-value',
        ])->assertStatus(503)->assertExactJson([
            'message' => 'Support authentication is not configured.',
        ]);
    }

    public function test_missing_application_encryption_key_fails_closed(): void
    {
        config(['app.key' => '']);

        $this->postJson('/api/support/login', [
            'username' => 'test-support',
            'password' => 'test-only-support-password',
        ])->assertStatus(503)->assertExactJson([
            'message' => 'Support authentication is not configured.',
        ]);
    }

    public function test_login_is_rate_limited_by_laravels_route_limiter(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/support/login', [
                'username' => 'test-support',
                'password' => 'wrong',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/support/login', [
            'username' => 'test-support',
            'password' => 'wrong',
        ])->assertTooManyRequests();
    }

    public function test_valid_cookie_allows_list_detail_and_session_endpoints(): void
    {
        $this->seed(SyntheticDataSeeder::class);
        $requestId = RefundRequest::query()->value('id');
        $cookie = $this->loginCookie();

        $this->withCredentials()->withUnencryptedCookie('refund_support_auth', $cookie)
            ->getJson('/api/refund-requests')->assertOk();
        $this->withCredentials()->withUnencryptedCookie('refund_support_auth', $cookie)
            ->getJson('/api/refund-requests/'.$requestId)->assertOk();
        $this->withCredentials()->withUnencryptedCookie('refund_support_auth', $cookie)
            ->getJson('/api/support/session')->assertOk()->assertExactJson(['authenticated' => true]);
    }

    public function test_tampered_and_malformed_cookies_are_rejected(): void
    {
        $cookie = $this->loginCookie();

        $this->withCredentials()->withUnencryptedCookie('refund_support_auth', $cookie.'tampered')
            ->getJson('/api/refund-requests')->assertUnauthorized();
        $this->withCredentials()->withUnencryptedCookie('refund_support_auth', 'not-an-encrypted-token')
            ->getJson('/api/refund-requests')->assertUnauthorized();
    }

    public function test_expired_cookie_is_rejected(): void
    {
        $expiredToken = Crypt::encryptString(json_encode([
            'purpose' => 'support-auth',
            'subject' => 'test-support',
            'expires_at' => now()->subSecond()->timestamp,
        ], JSON_THROW_ON_ERROR));

        $this->withCredentials()->withUnencryptedCookie('refund_support_auth', $expiredToken)
            ->getJson('/api/refund-requests')->assertUnauthorized();
    }

    public function test_logout_expires_cookie_and_authentication_is_required_afterward(): void
    {
        $cookie = $this->loginCookie();
        $this->withCredentials()->withUnencryptedCookie('refund_support_auth', $cookie)
            ->getJson('/api/refund-requests')->assertOk();

        $this->postJson('/api/support/logout')
            ->assertOk()
            ->assertExactJson(['authenticated' => false])
            ->assertCookieExpired('refund_support_auth');

        $this->withCredentials()->withUnencryptedCookies(['refund_support_auth' => ''])
            ->getJson('/api/refund-requests')->assertUnauthorized();
    }

    public function test_health_endpoint_remains_public(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJsonPath('database', 'connected');
    }

    public function test_cors_allows_configured_frontend_with_credentials(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'accept,content-type',
        ])->options('/api/refund-requests')
            ->assertNoContent()
            ->assertHeader('access-control-allow-origin', 'http://localhost:3000')
            ->assertHeader('access-control-allow-credentials', 'true');
    }

    private function loginCookie(): string
    {
        $response = $this->postJson('/api/support/login', [
            'username' => 'test-support',
            'password' => 'test-only-support-password',
        ])->assertOk();

        return $response->getCookie('refund_support_auth', false)->getValue();
    }
}
