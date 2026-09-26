<?php

namespace App\Domain\Refunds;

readonly class RefundDecision
{
    public function __construct(
        public RefundOutcome $outcome,
        public RefundPolicyReasonCode $reasonCode,
        public string $explanation,
    ) {}
}
