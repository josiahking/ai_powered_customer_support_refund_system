<?php

namespace Database\Seeders;

use App\Domain\Refunds\AiAnalysisStatus;
use App\Domain\Refunds\Money;
use App\Domain\Refunds\RefundPolicyEngine;
use App\Domain\Refunds\RefundPolicyInput;
use App\Domain\Refunds\RefundReason;
use App\Models\Customer;
use App\Models\Order;
use App\Models\RefundRequest;
use DateTimeImmutable;
use Illuminate\Database\Seeder;

class SyntheticDataSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            ['Avery Bennett', 'avery.bennett@example.test', 'WN-1001', 'Wireless Speaker', '89.90', 5, false],
            ['Jordan Brooks', 'jordan.brooks@example.test', 'WN-1002', 'Desk Lamp', '249.00', 10, false],
            ['Casey Chen', 'casey.chen@example.test', 'WN-1003', 'Coffee Grinder', '49.50', 3, false],
            ['Morgan Diaz', 'morgan.diaz@example.test', 'WN-1004', 'Standing Desk', '499.99', 14, false],
            ['Riley Edwards', 'riley.edwards@example.test', 'WN-1005', 'E-reader', '500.00', 7, false],
            ['Samir Farouk', 'samir.farouk@example.test', 'WN-1006', 'Camera Body', '750.00', 6, false],
            ['Taylor Grant', 'taylor.grant@example.test', 'WN-1007', 'Wool Coat', '129.00', 31, false],
            ['Jamie Hall', 'jamie.hall@example.test', 'WN-1008', 'Clearance Headphones', '59.00', 2, true],
            ['Robin Ito', 'robin.ito@example.test', 'WN-1009', 'Dining Chair', '180.00', 29, false],
            ['Cameron Jones', 'cameron.jones@example.test', 'WN-1010', 'Blender', '79.99', 12, false],
            ['Devon Kim', 'devon.kim@example.test', 'WN-1011', 'Monitor', '599.99', 2, false],
            ['Alex Laurent', 'alex.laurent@example.test', 'WN-1012', 'Running Shoes', '112.50', 9, false],
            ['Quinn Martin', 'quinn.martin@example.test', 'WN-1013', 'Desk Fan', '34.99', 45, false],
            ['Skylar Nordin', 'skylar.nordin@example.test', 'WN-1014', 'Camping Tent', '280.00', 18, false],
            ['Reese Okafor', 'reese.okafor@example.test', 'WN-1015', 'Cookbook Set', '65.00', 20, false],
        ];

        foreach ($profiles as $index => [$name, $email, $orderNumber, $itemName, $amount, $daysAgo, $finalSale]) {
            $customer = Customer::query()->updateOrCreate(
                ['email' => $email],
                ['name' => $name],
            );

            $customer->orders()->updateOrCreate(
                ['order_number' => $orderNumber],
                [
                    'item_name' => $itemName,
                    'total_amount' => $amount,
                    'ordered_at' => now()->subDays($daysAgo),
                    'final_sale' => $finalSale,
                ],
            );

            $customer->orders()->updateOrCreate(
                ['order_number' => sprintf('WN-%04d', 2001 + $index)],
                [
                    'item_name' => 'Everyday accessory',
                    'total_amount' => '24.95',
                    'ordered_at' => now()->subDays(90 + $index),
                    'final_sale' => false,
                ],
            );
        }

        $this->seedRefundExamples();
    }

    private function seedRefundExamples(): void
    {
        $examples = [
            ['WN-1001', '39.00', RefundReason::Damaged, 'Demo request: damaged speaker arrived cracked.'],
            ['WN-1002', '249.00', RefundReason::IncorrectItem, 'Demo request: the delivered lamp model was incorrect.'],
            ['WN-1005', '500.00', RefundReason::Damaged, 'Demo request: exact-threshold damaged e-reader.'],
            ['WN-1006', '600.00', RefundReason::Damaged, 'Demo request: high-value camera refund.'],
            ['WN-1007', '29.00', RefundReason::Damaged, 'Demo request: damaged coat reported outside the window.'],
            ['WN-1008', '59.00', RefundReason::Damaged, 'Demo request: clearance item marked final sale.'],
            ['WN-1009', '120.00', RefundReason::Damaged, 'Demo request: damaged chair within the 30-day window.'],
        ];

        foreach ($examples as [$orderNumber, $amount, $reason, $message]) {
            $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();
            $requestedMoney = Money::fromDecimal($amount);
            $decision = app(RefundPolicyEngine::class)->evaluate(new RefundPolicyInput(
                orderedAt: DateTimeImmutable::createFromInterface($order->ordered_at),
                evaluatedAt: DateTimeImmutable::createFromInterface(now()),
                finalSale: $order->final_sale,
                requestedAmountCents: $requestedMoney->cents,
                reason: $reason,
            ));

            RefundRequest::query()->updateOrCreate(
                [
                    'order_id' => $order->id,
                    'customer_message' => $message,
                ],
                [
                    'customer_id' => $order->customer_id,
                    'requested_amount' => $requestedMoney->toDecimal(),
                    'reason' => $reason,
                    'status' => $decision->outcome,
                    'policy_reason_code' => $decision->reasonCode,
                    'policy_explanation' => $decision->explanation,
                    'ai_status' => AiAnalysisStatus::NotAnalyzed,
                    'ai_analysis' => null,
                    'ai_provider' => null,
                    'ai_model' => null,
                    'ai_error_code' => null,
                    'policy_outcome' => $decision->outcome,
                    'resolution_reason_code' => $decision->reasonCode->value,
                    'resolution_explanation' => $decision->explanation,
                ],
            );
        }
    }
}
