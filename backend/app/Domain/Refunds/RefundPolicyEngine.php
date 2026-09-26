<?php

namespace App\Domain\Refunds;

use InvalidArgumentException;

class RefundPolicyEngine
{
    public function __construct(
        private readonly int $refundWindowDays,
        private readonly int $highValueThresholdCents,
    ) {
        if ($this->refundWindowDays < 1 || $this->highValueThresholdCents < 1) {
            throw new InvalidArgumentException('Refund policy thresholds must be positive.');
        }
    }

    public function evaluate(RefundPolicyInput $input): RefundDecision
    {
        if ($input->finalSale) {
            return new RefundDecision(
                RefundOutcome::Denied,
                RefundPolicyReasonCode::FinalSale,
                'This order was marked final sale and is not eligible for a refund.',
            );
        }

        if ($input->evaluatedAt > $input->orderedAt->modify("+{$this->refundWindowDays} days")) {
            return new RefundDecision(
                RefundOutcome::Denied,
                RefundPolicyReasonCode::RefundWindowExpired,
                "The refund request was submitted outside the {$this->refundWindowDays}-day refund window.",
            );
        }

        if ($input->suspicious) {
            return new RefundDecision(
                RefundOutcome::Escalated,
                RefundPolicyReasonCode::SuspiciousRequest,
                'This request requires human review because it was flagged as suspicious.',
            );
        }

        if ($input->conflictingClaims) {
            return new RefundDecision(
                RefundOutcome::Escalated,
                RefundPolicyReasonCode::ConflictingClaims,
                'This request requires human review because the customer claims conflict.',
            );
        }

        if ($input->requestedAmountCents > $this->highValueThresholdCents) {
            return new RefundDecision(
                RefundOutcome::Escalated,
                RefundPolicyReasonCode::HighValueReview,
                'This refund amount exceeds the automatic approval limit and requires human review.',
            );
        }

        if ($input->reason === RefundReason::Damaged) {
            return new RefundDecision(
                RefundOutcome::Approved,
                RefundPolicyReasonCode::EligibleDamagedItem,
                'The damaged item request is within the refund window and is eligible for a refund.',
            );
        }

        if ($input->reason === RefundReason::IncorrectItem) {
            return new RefundDecision(
                RefundOutcome::Approved,
                RefundPolicyReasonCode::EligibleIncorrectItem,
                'The incorrect item request is within the refund window and is eligible for a refund.',
            );
        }

        return new RefundDecision(
            RefundOutcome::Denied,
            RefundPolicyReasonCode::UnsupportedReason,
            'This request reason is not supported for automatic refund approval.',
        );
    }
}
