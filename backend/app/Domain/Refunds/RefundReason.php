<?php

namespace App\Domain\Refunds;

enum RefundReason: string
{
    case Damaged = 'DAMAGED';
    case IncorrectItem = 'INCORRECT_ITEM';
    case Other = 'OTHER';
}
