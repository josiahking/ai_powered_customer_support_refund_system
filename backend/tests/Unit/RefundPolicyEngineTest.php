<?php

namespace Tests\Unit;

use App\Domain\Refunds\RefundDecision;
use App\Domain\Refunds\RefundOutcome;
use App\Domain\Refunds\RefundPolicyEngine;
use App\Domain\Refunds\RefundPolicyInput;
use App\Domain\Refunds\RefundPolicyReasonCode;
use App\Domain\Refunds\RefundReason;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RefundPolicyEngineTest extends TestCase
{
    public function test_final_sale_is_denied_before_other_signals(): void
    {
        $decision = $this->decide(finalSale: true, suspicious: true);

        $this->assertDecision($decision, RefundOutcome::Denied, RefundPolicyReasonCode::FinalSale);
    }

    public function test_request_older_than_thirty_days_is_denied_before_escalation(): void
    {
        $decision = $this->decide(ageInDays: 31, requestedAmountCents: 60_000);

        $this->assertDecision($decision, RefundOutcome::Denied, RefundPolicyReasonCode::RefundWindowExpired);
    }

    public function test_exactly_thirty_days_with_damaged_reason_is_approved(): void
    {
        $decision = $this->decide(ageInDays: 30);

        $this->assertDecision($decision, RefundOutcome::Approved, RefundPolicyReasonCode::EligibleDamagedItem);
    }

    public function test_twenty_nine_days_with_incorrect_item_reason_is_approved(): void
    {
        $decision = $this->decide(ageInDays: 29, reason: RefundReason::IncorrectItem);

        $this->assertDecision($decision, RefundOutcome::Approved, RefundPolicyReasonCode::EligibleIncorrectItem);
    }

    public function test_exactly_five_hundred_dollars_is_not_escalated_for_value(): void
    {
        $decision = $this->decide(requestedAmountCents: 50_000);

        $this->assertDecision($decision, RefundOutcome::Approved, RefundPolicyReasonCode::EligibleDamagedItem);
    }

    public function test_amount_over_five_hundred_dollars_is_escalated(): void
    {
        $decision = $this->decide(requestedAmountCents: 50_001);

        $this->assertDecision($decision, RefundOutcome::Escalated, RefundPolicyReasonCode::HighValueReview);
    }

    public function test_suspicious_eligible_request_is_escalated(): void
    {
        $decision = $this->decide(suspicious: true);

        $this->assertDecision($decision, RefundOutcome::Escalated, RefundPolicyReasonCode::SuspiciousRequest);
    }

    public function test_conflicting_claims_are_escalated(): void
    {
        $decision = $this->decide(conflictingClaims: true);

        $this->assertDecision($decision, RefundOutcome::Escalated, RefundPolicyReasonCode::ConflictingClaims);
    }

    public function test_suspicious_signal_precedes_conflicting_claims(): void
    {
        $decision = $this->decide(suspicious: true, conflictingClaims: true);

        $this->assertDecision($decision, RefundOutcome::Escalated, RefundPolicyReasonCode::SuspiciousRequest);
    }

    public function test_conflicting_claims_precede_high_value_escalation(): void
    {
        $decision = $this->decide(conflictingClaims: true, requestedAmountCents: 50_001);

        $this->assertDecision($decision, RefundOutcome::Escalated, RefundPolicyReasonCode::ConflictingClaims);
    }

    public function test_unsupported_reason_is_denied(): void
    {
        $decision = $this->decide(reason: RefundReason::Other);

        $this->assertDecision($decision, RefundOutcome::Denied, RefundPolicyReasonCode::UnsupportedReason);
    }

    #[DataProvider('supportedApprovalReasons')]
    public function test_supported_reason_is_approved(RefundReason $reason, RefundPolicyReasonCode $reasonCode): void
    {
        $decision = $this->decide(reason: $reason);

        $this->assertDecision($decision, RefundOutcome::Approved, $reasonCode);
    }

    public static function supportedApprovalReasons(): array
    {
        return [
            'damaged item' => [RefundReason::Damaged, RefundPolicyReasonCode::EligibleDamagedItem],
            'incorrect item' => [RefundReason::IncorrectItem, RefundPolicyReasonCode::EligibleIncorrectItem],
        ];
    }

    public function test_policy_input_rejects_non_positive_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->decide(requestedAmountCents: 0);
    }

    private function decide(
        bool $finalSale = false,
        bool $suspicious = false,
        bool $conflictingClaims = false,
        int $ageInDays = 0,
        int $requestedAmountCents = 12_000,
        RefundReason $reason = RefundReason::Damaged,
    ): RefundDecision {
        $orderedAt = new DateTimeImmutable('2026-01-01T12:00:00+00:00');
        $evaluatedAt = $orderedAt->modify("+{$ageInDays} days");

        $input = new RefundPolicyInput(
            orderedAt: $orderedAt,
            evaluatedAt: $evaluatedAt,
            finalSale: $finalSale,
            requestedAmountCents: $requestedAmountCents,
            reason: $reason,
            suspicious: $suspicious,
            conflictingClaims: $conflictingClaims,
        );

        return (new RefundPolicyEngine(refundWindowDays: 30, highValueThresholdCents: 50_000))->evaluate($input);
    }

    private function assertDecision(
        RefundDecision $decision,
        RefundOutcome $outcome,
        RefundPolicyReasonCode $reasonCode,
    ): void {
        $this->assertSame($outcome, $decision->outcome);
        $this->assertSame($reasonCode, $decision->reasonCode);
        $this->assertNotSame('', $decision->explanation);
    }
}
