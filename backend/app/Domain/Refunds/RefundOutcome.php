<?php

namespace App\Domain\Refunds;

enum RefundOutcome: string
{
    case Approved = 'APPROVED';
    case Denied = 'DENIED';
    case Escalated = 'ESCALATED';
}
