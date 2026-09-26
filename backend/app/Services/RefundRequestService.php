<?php

namespace App\Services;

use App\Domain\Refunds\Money;
use App\Domain\Refunds\RefundPolicyEngine;
use App\Domain\Refunds\RefundPolicyInput;
use App\Domain\Refunds\RefundReason;
use App\Models\Order;
use App\Models\RefundRequest;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundRequestService
{
    public function __construct(private readonly RefundPolicyEngine $policyEngine) {}

    public function create(
        int $orderId,
        string $requestedAmount,
        RefundReason $reason,
        ?string $customerMessage,
    ): RefundRequest {
        return DB::transaction(function () use ($orderId, $requestedAmount, $reason, $customerMessage): RefundRequest {
            $order = Order::query()->findOrFail($orderId);
            $requestedMoney = Money::fromDecimal($requestedAmount);

            if ($requestedMoney->cents > Money::fromDecimal($order->total_amount)->cents) {
                throw ValidationException::withMessages([
                    'requested_amount' => 'The refund amount cannot exceed the order total.',
                ]);
            }

            $decision = $this->policyEngine->evaluate(new RefundPolicyInput(
                orderedAt: DateTimeImmutable::createFromInterface($order->ordered_at),
                evaluatedAt: DateTimeImmutable::createFromInterface(now()),
                finalSale: $order->final_sale,
                requestedAmountCents: $requestedMoney->cents,
                reason: $reason,
            ));

            return RefundRequest::query()->create([
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'requested_amount' => $requestedMoney->toDecimal(),
                'reason' => $reason,
                'customer_message' => $customerMessage,
                'status' => $decision->outcome,
                'policy_reason_code' => $decision->reasonCode,
                'policy_explanation' => $decision->explanation,
            ]);
        });
    }
}
