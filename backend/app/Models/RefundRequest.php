<?php

namespace App\Models;

use App\Domain\Refunds\RefundOutcome;
use App\Domain\Refunds\RefundPolicyReasonCode;
use App\Domain\Refunds\RefundReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundRequest extends Model
{
    protected $fillable = [
        'customer_id',
        'order_id',
        'requested_amount',
        'reason',
        'customer_message',
        'status',
        'policy_reason_code',
        'policy_explanation',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'reason' => RefundReason::class,
            'status' => RefundOutcome::class,
            'policy_reason_code' => RefundPolicyReasonCode::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
