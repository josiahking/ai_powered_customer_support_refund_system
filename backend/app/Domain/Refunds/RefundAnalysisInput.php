<?php

namespace App\Domain\Refunds;

use InvalidArgumentException;

readonly class RefundAnalysisInput
{
    public function __construct(
        public string $customerMessage,
        public ?RefundReason $customerReasonHint,
        public string $itemName,
        public string $requestedAmount,
        public string $orderAmount,
    ) {
        if (trim($this->customerMessage) === '') {
            throw new InvalidArgumentException('Customer message cannot be empty.');
        }
    }

    /** @return array<string, string> */
    public function toPromptContext(): array
    {
        return [
            'customer_message' => $this->customerMessage,
            'customer_reason_hint' => $this->customerReasonHint?->value ?? 'NOT_PROVIDED',
            'item_name' => $this->itemName,
            'requested_amount' => $this->requestedAmount,
            'order_amount' => $this->orderAmount,
        ];
    }
}
