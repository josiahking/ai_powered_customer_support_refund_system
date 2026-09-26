<?php

namespace App\Domain\Refunds;

enum RefundPolicyReasonCode: string
{
    case FinalSale = 'FINAL_SALE';
    case RefundWindowExpired = 'REFUND_WINDOW_EXPIRED';
    case SuspiciousRequest = 'SUSPICIOUS_REQUEST';
    case ConflictingClaims = 'CONFLICTING_CLAIMS';
    case HighValueReview = 'HIGH_VALUE_REVIEW';
    case EligibleDamagedItem = 'ELIGIBLE_DAMAGED_ITEM';
    case EligibleIncorrectItem = 'ELIGIBLE_INCORRECT_ITEM';
    case UnsupportedReason = 'UNSUPPORTED_REASON';
}
