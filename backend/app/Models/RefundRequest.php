<?php

namespace App\Models;

use App\Domain\Refunds\AiAnalysisStatus;
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
        'ai_status',
        'ai_analysis',
        'ai_provider',
        'ai_model',
        'ai_error_code',
        'policy_outcome',
        'resolution_reason_code',
        'resolution_explanation',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'reason' => RefundReason::class,
            'status' => RefundOutcome::class,
            'policy_reason_code' => RefundPolicyReasonCode::class,
            'ai_status' => AiAnalysisStatus::class,
            'ai_analysis' => 'array',
            'policy_outcome' => RefundOutcome::class,
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
