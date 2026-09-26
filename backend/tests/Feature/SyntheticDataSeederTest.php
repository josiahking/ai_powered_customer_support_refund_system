<?php

namespace Tests\Feature;

use App\Contracts\RefundAiAnalyzer;
use App\Domain\Refunds\RefundAnalysis;
use App\Domain\Refunds\RefundReason;
use App\Models\Customer;
use App\Models\Order;
use App\Models\RefundRequest;
use Database\Seeders\SyntheticDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FakeRefundAiAnalyzer;
use Tests\TestCase;

class SyntheticDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_deterministic_examples_without_invoking_ai(): void
    {
        config(['ai.openai.api_key' => '']);
        $analyzer = new FakeRefundAiAnalyzer(new RefundAnalysis(
            classifiedReason: RefundReason::Damaged,
            summary: 'The customer reports a damaged item.',
            suspicious: false,
            conflictingClaims: false,
            confidence: 0.9,
            suggestedResponse: 'We are reviewing the item report.',
        ));
        $this->app->instance(RefundAiAnalyzer::class, $analyzer);

        $this->seed(SyntheticDataSeeder::class);

        $this->assertSame([], $analyzer->inputs, 'Synthetic examples must not invoke AI analysis.');
        $this->assertSame(15, Customer::query()->count());
        $this->assertSame(30, Order::query()->count());
        $this->assertSame(7, RefundRequest::query()->count());

        $expectedExamples = [
            'WN-1001' => ['APPROVED', 'ELIGIBLE_DAMAGED_ITEM'],
            'WN-1002' => ['APPROVED', 'ELIGIBLE_INCORRECT_ITEM'],
            'WN-1005' => ['APPROVED', 'ELIGIBLE_DAMAGED_ITEM'],
            'WN-1006' => ['ESCALATED', 'HIGH_VALUE_REVIEW'],
            'WN-1007' => ['DENIED', 'REFUND_WINDOW_EXPIRED'],
            'WN-1008' => ['DENIED', 'FINAL_SALE'],
            'WN-1009' => ['APPROVED', 'ELIGIBLE_DAMAGED_ITEM'],
        ];

        foreach ($expectedExamples as $orderNumber => [$outcome, $reasonCode]) {
            $record = DB::table('refund_requests')
                ->join('orders', 'refund_requests.order_id', '=', 'orders.id')
                ->where('orders.order_number', $orderNumber)
                ->first();

            $this->assertNotNull($record, "Missing seeded request for {$orderNumber}.");
            $this->assertSame($outcome, $record->status, "Unexpected outcome for {$orderNumber}.");
            $this->assertSame($reasonCode, $record->policy_reason_code, "Unexpected policy reason for {$orderNumber}.");
            $this->assertSame($record->status, $record->policy_outcome);
            $this->assertSame($record->policy_reason_code, $record->resolution_reason_code);
            $this->assertSame($record->policy_explanation, $record->resolution_explanation);
            $this->assertSame('NOT_ANALYZED', $record->ai_status);
            $this->assertNull($record->ai_analysis);
            $this->assertNull($record->ai_provider);
            $this->assertNull($record->ai_model);
            $this->assertNull($record->ai_error_code);
        }

        $this->seed(SyntheticDataSeeder::class);

        $this->assertSame([], $analyzer->inputs, 'Rerunning the seeder must remain AI-free.');
        $this->assertSame(15, Customer::query()->count());
        $this->assertSame(30, Order::query()->count());
        $this->assertSame(7, RefundRequest::query()->count());
    }
}
