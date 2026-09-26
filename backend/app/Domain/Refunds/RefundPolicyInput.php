<?php

namespace App\Domain\Refunds;

use DateTimeImmutable;
use InvalidArgumentException;

readonly class RefundPolicyInput
{
    public function __construct(
        public DateTimeImmutable $orderedAt,
        public DateTimeImmutable $evaluatedAt,
        public bool $finalSale,
        public int $requestedAmountCents,
        public RefundReason $reason,
        public bool $suspicious = false,
        public bool $conflictingClaims = false,
    ) {
        if ($this->requestedAmountCents <= 0) {
            throw new InvalidArgumentException('Requested amount must be greater than zero.');
        }

        if ($this->orderedAt > $this->evaluatedAt) {
            throw new InvalidArgumentException('Order date cannot be in the future.');
        }
    }
}
