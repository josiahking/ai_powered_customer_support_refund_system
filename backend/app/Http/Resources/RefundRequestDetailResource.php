<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundRequestDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requested_amount' => $this->requested_amount,
            'customer_reason_hint' => $this->reason->value,
            'customer_message' => $this->customer_message,
            'created_at' => $this->created_at->toISOString(),
            'customer' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ],
            'order' => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'item_name' => $this->order->item_name,
                'total_amount' => $this->order->total_amount,
                'ordered_at' => $this->order->ordered_at->toISOString(),
                'final_sale' => $this->order->final_sale,
            ],
            'ai' => [
                'status' => $this->ai_status->value,
                'analysis' => $this->ai_analysis,
                'provider' => $this->ai_provider,
                'model' => $this->ai_model,
                'error_code' => $this->ai_error_code,
            ],
            'policy' => [
                'outcome' => $this->policy_outcome?->value,
                'reason_code' => $this->policy_reason_code->value,
                'explanation' => $this->policy_explanation,
            ],
            'resolution' => [
                'outcome' => $this->status->value,
                'reason_code' => $this->resolution_reason_code,
                'explanation' => $this->resolution_explanation,
            ],
        ];
    }
}
